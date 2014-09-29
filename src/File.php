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
        $this->file = new \SplFileObject($path, 'w+');
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
        return $this->file->getSize();
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
        $this->file->fseek(0, SEEK_END);

        $position = $this->file->ftell();

        $value = json_encode($value);

        $this->file->fwrite(
            pack('N*', strlen($key)) .
            pack('N*', strlen($value)) .
            $key .
            $value
        );

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
        $metadata = $this->getMetadata($position);

        $this->file->fseek($metadata['klen'], SEEK_CUR);
        $value = $this->file->fread($metadata['vlen']);

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
        $file = fopen($this->file->getPathname(), 'w+');

        // Find the current record and advance the pointer beyond it
        $metadata = $this->getMetadata($position);
        fseek($file, $metadata['len'], SEEK_CUR);

        // Push the end of the file into temp
        $temp = fopen('php://memory', 'w+');
        stream_copy_to_stream($file, $temp);

        // Reset back to original position and overwrite it
        fseek($file, $position);

        rewind($temp);
        stream_copy_to_stream($temp, $file);

        unset($temp, $file);
    }

    /**
     * Truncates the collection, removing all the data from the file
     *
     * @return bool
     */
    public function truncate()
    {
        return $this->file->ftruncate(0);
    }


    /**
     * Index the file by getting key/value positions within the file
     *
     * @return array
     */
    public function buildIndex()
    {
        $this->file->fseek(0);

        $indexes = [];
        while (!$this->file->eof()) {
            $position = $this->file->ftell();

            // Grab key and value lengths - if its the last (empty) line, break
            if (!$metadata = $this->getMetadata()) {
                break;
            }

            // Gets the key and adds the key and position to the array
            $indexes[$this->file->fread($metadata['klen'])] = $position;

            //Skip over the value, to the next key/value pair
            $this->file->fseek($metadata['vlen'], SEEK_CUR);
        }

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

            return ['klen' => $klen, 'vlen' => $vlen, 'len' => $klen + $vlen];
        }

        return false;
    }
}
