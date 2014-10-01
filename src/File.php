<?php

namespace SlowDB;

use Symfony\Component\Filesystem\Filesystem;

class File
{
    /**
     * A full filepath
     *
     * @var SplFileObject
     */
    private $file;

    /**
     * Constructor
     *
     * @param string  $file  The filename to store data in
     */
    public function __construct($path)
    {
        $mode = file_exists($path) ? 'rb+' : 'wb+';
        $this->file = new \SplFileObject($path, $mode);
    }

    /**
     * Decontructor
     *
     * A 'Just In Case' to clear our resource handler on our file
     *
     * @return type
     */
    public function __destruct()
    {
        unset($this->file);
    }

    /**
     * Returns the filepath of the database file
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->file->getPathname();
    }

    /**
     * Returns the current size of the database file
     *
     * @return integer
     */
    public function getFileSize()
    {
        return filesize($this->file->getPathname());
    }

    /**
     * Write the new record to the database file
     *
     * We store metadata (lengths) in the first 8 bytes of each row
     * allowing us to use binary searches and indexing key/value positions
     *
     * @param  string  $key   The Key to be stored
     * @param  mixed   $value The Value to be stored
     *
     * @return integer        Returns the position of the data
     */
    public function insert($key, $value)
    {
        $this->file->flock(LOCK_SH);
        $this->file->fseek(0, SEEK_END);

        $position = $this->file->ftell();

        $value = json_encode($value);

        $this->file->flock(LOCK_EX);
        $this->file->fwrite(
            pack('N*', strlen($key)) .
            pack('N*', strlen($value)) .
            $key .
            $value
        );
        $this->file->flock(LOCK_UN);

        return $position;
    }

    /**
     * Retrieves the data from the database for a Key based on position
     *
     * @param integer $position The offset in the file where the data is stored
     *
     * @return array
     */
    public function read($position)
    {
        $this->file->flock(LOCK_SH);
        $metadata = $this->getMetadata($position);

        $this->file->fseek($metadata->klen, SEEK_CUR);
        $value = $this->file->fread($metadata->vlen);
        $this->file->flock(LOCK_UN);

        return json_decode($value, true);
    }

    /**
     * Updates an existing key by removing it from the existing file and
     * appending the new value to the end of the file.
     *
     * @param  string  $key   The Key to be stored
     * @param  mixed   $value The Value to be stored
     *
     * @return integer
     */
    public function update($position, $key, $value)
    {
        $this->remove($position);

        return $this->insert($key, $value);
    }

    /**
     * Removes a Key/Value pair based on its position in the file
     *
     * @param  integer $position The offset position in the file
     */
    public function remove($position)
    {
        $temp = new \SplTempFileObject(-1);

        $this->file->flock(LOCK_EX);

        $filesize = $this->file->getSize();
        $metadata = $this->getMetadata($position);

        // Seek past the document we want to remove
        $this->file->fseek($metadata->length, SEEK_CUR);

        // Write everything after the target document to memory
        $temp->fwrite($this->file->fread($filesize));

        // Clear the file up to the target document
        $this->file->ftruncate($position);

        // Write Temp back to the end of the file
        $temp->fseek(0);
        $this->file->fseek(0, SEEK_END);
        $this->file->fwrite($temp->fread($filesize));

        $this->file->flock(LOCK_UN);
    }

    /**
     * Truncates the collection, removing all the data from the file
     *
     * @return bool
     */
    public function truncate()
    {
        $this->file->flock(LOCK_EX);
        $result = $this->file->ftruncate(0);
        $this->file->flock(LOCK_UN);

        return $result;
    }


    /**
     * Index the file by getting key/value positions within the file
     *
     * @return array
     */
    public function buildIndex()
    {
        $this->file->flock(LOCK_SH);
        $this->file->fseek(0);

        $indexes = [];
        while (!$this->file->eof()) {
            $position = $this->file->ftell();

            // Grab key and value lengths - if its the last (empty) line, break
            if (!$metadata = $this->getMetadata()) {
                break;
            }

            // Gets the key and adds the key and position to the array
            $indexes[$this->file->fread($metadata->klen)] = $position;
            //Skip over the value, to the next key/value pair
            $this->file->fseek($metadata->vlen, SEEK_CUR);
        }
        $this->file->flock(LOCK_UN);

        return $indexes;
    }

    /**
     * Retrieves the Metadata for a Key/Value pair
     *
     * Optionally allows a specific position offset in the file
     *
     * @param  integer $position  An offset to seek to in a file
     *
     * @return array|bool
     */
    protected function getMetadata($position = null)
    {
        if (!is_null($position)) {
            $this->file->fseek($position);
        }

        $metadata = $this->file->fread(8);
        if ($metadata) {
            list(, $klen, $vlen) = unpack('N*', $metadata);

            return (object) ['klen' => $klen, 'vlen' => $vlen, 'length' => $klen + $vlen];
        }

        return false;
    }
}
