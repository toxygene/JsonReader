<?php
namespace Toxygene\JsonReader\Tests;

use Toxygene\JsonReader\JsonReader;

class JsonReaderTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var resource
     */
    private $stream;

    /**
     * @var JsonReader
     */
    private $reader;

    public function setUp()
    {
        $this->stream = fopen('php://memory', 'w+');

        $this->reader = new JsonReader();
        $this->reader->openStream($this->stream);
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

    private function setStreamContents($contents)
    {
        fseek($this->stream, 0);
        fwrite($this->stream, $contents);
        fseek($this->stream, 0);

        return $this;
    }

    private function assertReadNode($type, $value = null)
    {
        $this->assertTrue($this->reader->read());

        $this->assertEquals(
            $type,
            $this->reader->tokenType
        );

        $this->assertEquals(
            $value,
            $this->reader->value
        );
    }

    public function testAnObjectCanBeRead()
    {
        $this->setStreamContents('{"key": "value"}');

        $this->assertReadNode(JsonReader::OBJECT_START);
        $this->assertReadNode(JsonReader::OBJECT_KEY, 'key');
        $this->assertReadNode(JsonReader::STRING, 'value');
        $this->assertReadNode(JsonReader::OBJECT_END);
        $this->assertFalse($this->reader->read());
    }

    public function testAnArrayCanBeRead()
    {
        $this->setStreamContents('["value"]');

        $this->assertReadNode(JsonReader::ARRAY_START);
        $this->assertReadNode(JsonReader::STRING, 'value');
        $this->assertReadNode(JsonReader::ARRAY_END);
        $this->assertFalse($this->reader->read());
    }

    public function testNestedObjectsAndArraysCanBeRead()
    {
        $this->setStreamContents('{"one":[{"two": "three"},{"four": "five"}]}');

        $this->assertReadNode(JsonReader::OBJECT_START);
        $this->assertReadNode(JsonReader::OBJECT_KEY, 'one');
        $this->assertReadNode(JsonReader::ARRAY_START);
        $this->assertReadNode(JsonReader::OBJECT_START);
        $this->assertReadNode(JsonReader::OBJECT_KEY, 'two');
        $this->assertReadNode(JsonReader::STRING, 'three');
        $this->assertReadNode(JsonReader::OBJECT_END);
        $this->assertReadNode(JsonReader::OBJECT_START);
        $this->assertReadNode(JsonReader::OBJECT_KEY, 'four');
        $this->assertReadNode(JsonReader::STRING, 'five');
        $this->assertReadNode(JsonReader::OBJECT_END);
        $this->assertReadNode(JsonReader::ARRAY_END);
        $this->assertReadNode(JsonReader::OBJECT_END);
        $this->assertFalse($this->reader->read());
    }

    public function testInvalidNestingSyntaxThrowsAnException()
    {
        $this->setExpectedException('RuntimeException');

        $this->setStreamContents('{]');

        $this->assertReadNode(JsonReader::OBJECT_START);
        $this->reader->read();
    }

    public function testStructsAreTracked()
    {
        $this->setStreamContents('{"one":[{},[]]}');

        $this->assertReadNode(JsonReader::OBJECT_START);
        $this->assertStruct(1, JsonReader::OBJECT);

        $this->assertReadNode(JsonReader::OBJECT_KEY, 'one');

        $this->assertReadNode(JsonReader::ARRAY_START);
        $this->assertStruct(2, JsonReader::ARR);

        $this->assertReadNode(JsonReader::OBJECT_START);
        $this->assertStruct(3, JsonReader::OBJECT);

        $this->assertReadNode(JsonReader::OBJECT_END);
        $this->assertStruct(2, JsonReader::ARR);

        $this->assertReadNode(JsonReader::ARRAY_START);
        $this->assertStruct(3, JsonReader::ARR);

        $this->assertReadNode(JsonReader::ARRAY_END);
        $this->assertStruct(2, JsonReader::ARR);

        $this->assertReadNode(JsonReader::ARRAY_END);
        $this->assertStruct(1, JsonReader::OBJECT);

        $this->assertReadNode(JsonReader::OBJECT_END);
        $this->assertStruct(0, NULL);
    }

    public function testNumbersAreParsed()
    {
        $this->setStreamContents('{"test": -1.1e+1}');

        $this->assertReadNode(JsonReader::OBJECT_START);
        $this->assertReadNode(JsonReader::OBJECT_KEY, 'test');
        $this->assertReadNode(JsonReader::FLOAT, '-1.1e+1');
        $this->assertReadNode(JsonReader::OBJECT_END);
    }

    private function assertStruct($depth, $type)
    {
        $this->assertEquals($depth, $this->reader->currentDepth);
        $this->assertEquals($type, $this->reader->currentStruct);
    }

}