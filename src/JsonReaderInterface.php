<?php
namespace Toxygene\JsonReader;

use Toxygene\StreamReader\PeekableStreamReaderInterface;

interface JsonReaderInterface
{

    /**
     * Constructor
     *
     * @param PeekableStreamReaderInterface $stream
     */
    public function __construct(PeekableStreamReaderInterface $stream);

    /**
     * Read from the stream until a token is read
     *
     * @return boolean
     */
    public function read();

}