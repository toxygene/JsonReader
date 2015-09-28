<?php
namespace Toxygene\JsonReader;

use RuntimeException;
use SplStack;
use Toxygene\StreamReader\PeekableStreamReaderDecorator;
use Toxygene\StreamReader\PeekableStreamReaderInterface;
use Toxygene\StreamReader\StreamReader;
use Toxygene\StreamReader\StreamReaderInterface;

class JsonReader implements JsonReaderInterface
{

    const NULL = 1;
    const FALSE = 2;
    const TRUE = 4;
    const INT = 8;
    const FLOAT = 16;
    const STRING = 32;
    const ARRAY_START = 64;
    const ARRAY_END = 128;
    const OBJECT_START = 256;
    const OBJECT_KEY = 512;
    const OBJECT_END = 1024;

    const ARR = 'array';
    const OBJECT = 'object';

    const BOOLEAN = self::FALSE & self::TRUE;
    const NUMBER = self::INT & self::FLOAT;
    const VALUE = self::NULL & self::FALSE & self::TRUE & self::INT & self::FLOAT & self::STRING;

    /**
     * Current depth
     *
     * @var integer
     */
    public $currentDepth = 0;

    /**
     * Current token type
     *
     * @var integer
     */
    public $tokenType;

    /**
     * Current token value
     *
     * @var mixed
     */
    public $value;

    /**
     * Current struct
     *
     * @var string
     */
    public $currentStruct;

    /**
     * @var PeekableStreamReaderInterface
     */
    private $stream;

    /**
     * @var SplStack
     */
    private $structStack;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->structStack = new SplStack();
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return $this->stream
            ->close();
    }

    /**
     * {@inheritdoc}
     */
    public function open($uri)
    {
        return $this->openStream(
            fopen($uri, 'r')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function openStream($stream)
    {
        return $this->openStreamReader(
            new StreamReader($stream)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function openStreamReader(StreamReaderInterface $streamReader)
    {
        return $this->openPeekableStreamReader(
            new PeekableStreamReaderDecorator($streamReader)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function openPeekableStreamReader(PeekableStreamReaderInterface $peekableStreamReader)
    {
        $this->stream = $peekableStreamReader;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read()
    {
        while (!$this->stream->isEmpty()) {
            $char = $this->stream->readChar();

            switch ($char) {
                case '"':
                    $this->readString();
                    return true;

                case '{':
                    $this->pushStructToken(self::OBJECT_START);
                    return true;

                case '}':
                    $this->popStructToken(self::OBJECT_END);
                    return true;

                case '[':
                    $this->pushStructToken(self::ARRAY_START);
                    return true;

                case ']':
                    $this->popStructToken(self::ARRAY_END);
                    return true;

                case ' ':
                case ':':
                case ',':
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

    /**
     *
     */
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
                    $this->setTokenData($this->determineStringType(), $string);
                    break 2;

                default:
                    $string .= $char;
                    break;
            }
        }
    }

    /**
     *
     */
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

    /**
     *
     */
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
     * Set the current token data
     *
     * @param string $type
     * @param mixed $value
     * @return $this
     */
    private function setTokenData($type, $value = null)
    {
        $this->tokenType = $type;
        $this->value = $value;

        return $this;
    }

    private function determineStringType()
    {
        while (!$this->stream->isPeekEmpty()) {
            $char = $this->stream->peek(1);

            switch ($char) {
                case ',':
                case '}':
                case ']':
                    return self::STRING;

                case ':':
                    return self::OBJECT_KEY;

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

        return self::STRING;
    }

    /**
     * @param string $token
     */
    private function pushStructToken($token)
    {
        switch ($token) {
            case self::ARRAY_START:
                $this->structStack
                    ->push(self::ARR);
                break;

            case self::OBJECT_START:
                $this->structStack
                    ->push(self::OBJECT);
                break;
        }

        $this->currentStruct = $this->structStack
            ->top();

        $this->currentDepth = $this->structStack
            ->count();

        $this->setTokenData($token);
    }

    /**
     * @param string $token
     */
    private function popStructToken($token)
    {
        $topToken = $this->structStack
            ->top();

        switch ($token) {
            case self::ARRAY_END:
                if ($topToken == self::OBJECT) {
                    throw new RuntimeException('Expected ], got }');
                }
                break;

            case self::OBJECT_END:
                if ($topToken == self::ARR) {
                    throw new RuntimeException('Expected }, got ]');
                }
                break;
        }

        $this->currentStruct = $this->structStack
            ->pop();

        $this->currentDepth = $this->structStack
            ->count();

        $this->setTokenData($token);
    }

}