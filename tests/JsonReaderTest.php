<?php
namespace Toxygene\JsonReader\Tests;

use Toxygene\JsonReader\JsonReader;
use Toxygene\StreamReader\PeekableStreamReaderDecorator;
use Toxygene\StreamReader\StreamReader;

class JsonReaderTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var
     */
    private $stream;

    /**
     * @var JsonReader
     */
    private $reader;

    public function setUp()
    {
        $this->stream = fopen('php://memory', 'w+');
        $this->reader = new JsonReader(new PeekableStreamReaderDecorator(new StreamReader($this->stream)));
    }

    public function tearDown()
    {
        unset($this->reader);
        fclose($this->stream);
    }

    public function testTest()
    {
        $this->setStreamContents('{"key": "value"}');

        $this->assertTrue($this->reader->read());

        $this->assertEquals(
            JsonReader::OBJECT_START,
            $this->reader->nodeType
        );

        $this->assertTrue($this->reader->read());

        $this->assertEquals(
            JsonReader::MEMBER_NAME,
            $this->reader->nodeType
        );

        $this->assertEquals(
            'key',
            $this->reader->nodeValue
        );

        $this->assertTrue($this->reader->read());

        $this->assertEquals(
            JsonReader::STRING,
            $this->reader->nodeType
        );

        $this->assertEquals(
            'value',
            $this->reader->nodeValue
        );

        $this->assertTrue($this->reader->read());

        $this->assertEquals(
            JsonReader::OBJECT_END,
            $this->reader->nodeType
        );
    }

    private function setStreamContents($contents)
    {
        fseek($this->stream, 0);
        fwrite($this->stream, $contents);
        fseek($this->stream, 0);

        return $this;
    }

}