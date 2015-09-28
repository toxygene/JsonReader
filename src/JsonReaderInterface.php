<?php
namespace Toxygene\JsonReader;

use Toxygene\StreamReader\PeekableStreamReaderInterface;
use Toxygene\StreamReader\StreamReaderInterface;

interface JsonReaderInterface
{

    /**
     * Close the stream
     *
     * @return boolean
     */
    public function close();

    /**
     * Open a URI
     *
     * @param string $uri
     * @return boolean
     */
    public function open($uri);

    /**
     * Open stream
     *
     * @param resource $stream
     * @return boolean
     */
    public function openStream($stream);

    /**
     * Open a stream reader
     *
     * @param StreamReaderInterface $streamReader
     * @return boolean
     */
    public function openStreamReader(StreamReaderInterface $streamReader);

    /**
     * Open a peekable stream reader
     *
     * @param PeekableStreamReaderInterface $peekableStreamReader
     * @return boolean
     */
    public function openPeekableStreamReader(PeekableStreamReaderInterface $peekableStreamReader);

    /**
     * Read from the stream until a token is read
     *
     * @return boolean
     */
    public function read();

}