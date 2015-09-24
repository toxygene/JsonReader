<?php
namespace Toxygene\JsonReader;

use RuntimeException;
use Toxygene\StreamReader\PeekableStreamReaderInterface;

class JsonReader implements JsonReaderInterface
{

    const STRING = 1;
    const MEMBER_NAME = 2;
    const OBJECT_START = 3;
    const OBJECT_END = 4;
    const ARRAY_START = 5;
    const ARRAY_END = 6;
    public $nodeType;
    public $nodeValue;
    /**
     * @var PeekableStreamReaderInterface
     */
    private $stream;

    /**
     * Constructor
     *
     * @param PeekableStreamReaderInterface $stream
     */
    public function __construct(PeekableStreamReaderInterface $stream)
    {
        $this->stream = $stream;
    }

    /**
     * Read from the stream until a token is read
     *
     * @return boolean
     */
    public function read()
    {
        while (true) {
            $char = $this->stream->readChar();

            switch ($char) {
                case '"':
                    $this->readString();
                    return true;

                case '{':
                    $this->setNodeData(self::OBJECT_START);
                    return true;

                case '}':
                    $this->setNodeData(self::OBJECT_END);
                    return true;

                case '[':
                    $this->setNodeData(self::ARRAY_START);
                    return true;

                case ']':
                    $this->setNodeData(self::ARRAY_END);
                    return true;

                case ' ':
                case ':':
                    break;

                default:
                    throw new RuntimeException(sprintf(
                        'Unexpected %s',
                        $char
                    ));
            }
        }

        throw new RuntimeException();
    }

    private function readString()
    {
        $string = '';

        while (true) {
            $char = $this->stream->readChar();

            switch ($char) {
                case '\\':
                    $this->parseEscapeSequence();
                    break;

                case '"':
                    $this->setNodeData($this->determineStringType(), $string);
                    break 2;

                default:
                    $string .= $char;
                    break;
            }
        }
    }

    private function parseEscapeSequence()
    {
        $char = $this->stream->readChar();

        switch ($char) {
            case '"':
            case '\\':
            case 'b':
            case 'f':
            case 'n':
            case 'r':
            case 't':
                break;

            case 'u':
                $this->parseUnicodeEscapeSequence();
                break;

            default:
                throw new RuntimeException(sprintf(
                    'Unexpected %s, expected valid escape sequence',
                    $char
                ));
        }
    }

    private function parseUnicodeEscapeSequence()
    {
        for ($i = 0; $i < 4; ++$i) {
            $char = $this->stream->readChar();

            switch (strtolower($char)) {
                case '0':
                case '1':
                case '2':
                case '3':
                case '4':
                case '5':
                case '6':
                case '7':
                case '8':
                case '9':
                case 'a':
                case 'b':
                case 'c':
                case 'd':
                case 'e':
                case 'f':
                    break;

                default:
                    throw new RuntimeException(sprintf(
                        'Unexpected %s, expected hexadecimal character',
                        $char
                    ));
            }
        }
    }

    /**
     * @param string $type
     * @param mixed $value
     * @return $this
     */
    private function setNodeData($type, $value = null)
    {
        $this->nodeType = $type;
        $this->nodeValue = $value;
        return $this;
    }

    private function determineStringType()
    {
        while (true) {
            $char = $this->stream->peek(1);

            switch ($char) {
                case ',':
                case '}':
                case ']':
                    return self::STRING;

                case ':':
                    return self::MEMBER_NAME;

                case "\t":
                case "\r":
                case "\n":
                case "\f":
                case " ":
                    break;

                default:
                    throw new RuntimeException(sprintf(
                        'Unexpected %s, expected : or ,',
                        $char
                    ));
            }
        }

        throw new RuntimeException();
    }

}