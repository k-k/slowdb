<?php

namespace SlowDB;

use Doctrine\Common\Collections\ArrayCollection;
use SlowDB\File;

/**
 * Collection Class
 *
 * @author Keith Kirk <keith@kmfk.io>
 */
class Collection
{
    /**
     * The collection name
     *
     * @var string
     */
    public $name;

    /**
     * The Database File
     *
     * @var File
     */
    private $file;

    /**
     * An index of keys to their respective file line
     *
     * @var ArrayCollection
     */
    private $index;

    /**
     * Constructor
     *
     * @param string $name  The collection name
     * @param string $path  The file path for the database file
     */
    public function __construct($name, $path)
    {
        $this->name = $name;
        $this->file = new File($path);

        $this->rebuildIndex();
    }

    /**
     * Returns basic information about the collection
     *
     * @return array
     */
    public function info()
    {
        return ['count' => $this->count(), 'size' => $this->file->getFileSize()];
    }

    /**
     * Returns all Key/Value pairs in the Colleciton
     *
     * @return array
     */
    public function all()
    {
        $results = [];
        foreach ($this->index->getKeys() as $key) {
            $result = $this->get($key);
            $results[] = [key($result) => current($result)];
        }

        return $results;
    }

    /**
     * Retrieves the value based on the key
     *
     * @param  mixed $key The key to fetch data for
     *
     * @return array
     */
    public function get($key)
    {
        $position = $this->getPosition($key);
        if (false !== $position) {
            return [$key => $this->file->read($position)];
        }

        return false;
    }

    /**
     * Returns a count of Key/Value pairs in the Collection
     *
     * Optionally you can pass a an case-insensitive Expression to filter the
     * count by
     *
     * @param  string  $match An expression to filter on
     * @param  boolean $exact Whether to do an exact match
     *
     * @return integer
     */
    public function count($match = null, $exact = false)
    {
        if (!is_null($match)) {
            $keys = $this->index->getKeys();

            if ($exact) {
                $match = "^{$match}$";
            }

            $matches = array_filter($keys, function($key) use ($match) {
                return preg_match("/{$match}/i", $key);
            });

            return sizeof($matches);
        }

        return $this->index->count();
    }

    /**
     * Queries for Keys matching a case insensitive expression
     *
     * @param  string $match The expression to match against
     *
     * @return array
     */
    public function query($match)
    {
        $keys    = $this->index->getKeys();
        $matches = array_filter($keys, function($key) use ($match) {
            return preg_match("/{$match}/i", $key);
        });

        $results = [];
        foreach ($matches as $match) {
            $result = $this->get($match);
            $results[] = [key($result) => current($result)];
        }

        return $results;
    }

    /**
     * Sets the Key/Value pair in the database
     *
     * @param mixed $key   The key to store
     * @param mixed $value The value to store
     *
     * @return bool
     */
    public function set($key, $value)
    {
        $position = $this->getPosition($key);
        if (false !== $position) {
            $this->file->update($position, $key, $value);
            $this->rebuildIndex();
        } else {
            $pos = $this->file->insert($key, $value);
            $this->index->set($key, $pos);
        }

        return true;
    }

    /**
     * Remove a Value based on its Key
     *
     * @param  mixed  $key The key to remove
     *
     * @return bool
     */
    public function remove($key)
    {
        $position = $this->getPosition($key);
        if (false !== $position) {
            $this->file->remove($position);
            $this->rebuildIndex();

            return true;
        }

        return false;
    }

    /**
     * Clears all data from the table
     *
     * @return bool
     */
    public function truncate()
    {
        $this->index->clear();
        $this->file->truncate();

        return true;
    }

    /**
     * Removes the Collection database file
     *
     * @return bool
     */
    public function drop()
    {
        $filepath = $this->file->getFilePath();

        unset($this->file);

        if (!file_exists($filepath)) {
            unlink($filepath);
        }

        return true;
    }

    /**
     * Get a Key/Value position by Key, if it exists - returns false if the
     * key does not exist
     *
     * @param  mixed  $key The key to search the index for
     *
     * @return integer|bool
     */
    private function getPosition($key)
    {
        if ($this->index->containsKey($key)) {
            return $this->index->get($key);
        }

        return false;
    }

    /**
     * Traverses the file to build the Index
     */
    private function rebuildIndex()
    {
        $this->index = new ArrayCollection($this->file->buildIndex());
    }
}
