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

    public function testAStringCanBeRead()
    {
        $this->setStreamContents('"value"');

        $this->assertReadNode(JsonReader::STRING, 'value');
    }

    public function testAnObjectCanBeRead()
    {
        $this->setStreamContents('{"key": "value"}');

        $this->assertReadNode(JsonReader::OBJECT_START);
        $this->assertReadNode(JsonReader::MEMBER_NAME, 'key');
        $this->assertReadNode(JsonReader::STRING, 'value');
        $this->assertReadNode(JsonReader::OBJECT_END);
    }

    public function testAnArrayCanBeRead()
    {
        $this->setStreamContents('["value"]');

        $this->assertReadNode(JsonReader::ARRAY_START);
        $this->assertReadNode(JsonReader::STRING, 'value');
        $this->assertReadNode(JsonReader::ARRAY_END);
    }

    public function testNestedObjectsAndArraysCanBeRead()
    {
        $this->setStreamContents('{"one":[{"two": "three"},{"four": "five"}]}');

        $this->assertReadNode(JsonReader::OBJECT_START);
        $this->assertReadNode(JsonReader::MEMBER_NAME, 'one');
        $this->assertReadNode(JsonReader::ARRAY_START);
        $this->assertReadNode(JsonReader::OBJECT_START);
        $this->assertReadNode(JsonReader::MEMBER_NAME, 'two');
        $this->assertReadNode(JsonReader::STRING, 'three');
        $this->assertReadNode(JsonReader::OBJECT_END);
        $this->assertReadNode(JsonReader::OBJECT_START);
        $this->assertReadNode(JsonReader::MEMBER_NAME, 'four');
        $this->assertReadNode(JsonReader::STRING, 'five');
        $this->assertReadNode(JsonReader::OBJECT_END);
        $this->assertReadNode(JsonReader::ARRAY_END);
        $this->assertReadNode(JsonReader::OBJECT_END);
    }

    private function assertReadNode($type, $value = null)
    {
        $this->assertTrue($this->reader->read());

        $this->assertEquals(
            $type,
            $this->reader->nodeType
        );

        $this->assertEquals(
            $value,
            $this->reader->nodeValue
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