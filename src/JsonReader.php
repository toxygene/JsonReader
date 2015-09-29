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

    const BOOLEAN = self::FALSE | self::TRUE;
    const NUMBER = self::INT | self::FLOAT;
    const VALUE = self::NULL | self::BOOLEAN | self::NUMBER | self::STRING;

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
        $this->stream->resetPeek();

        if ($this->structStack->isEmpty()) {
            $this->currentDepth = 0;
            $this->currentStruct = null;
        } else {
            $this->currentDepth = $this->structStack->count();
            $this->currentStruct = $this->structStack->top();
        }

        while (!$this->stream->isEmpty()) {
            $peek = $this->stream->peek();

            switch ($peek) {
                case '"':
                    $this->stream->readCharsToPeek();
                    $this->readString();
                    return true;

                case '{':
                    $this->stream->readCharsToPeek();
                    $this->pushStructToken(self::OBJECT_START);
                    return true;

                case '}':
                    $this->stream->readCharsToPeek();
                    $this->popStructToken(self::OBJECT_END);
                    return true;

                case '[':
                    $this->stream->readCharsToPeek();
                    $this->pushStructToken(self::ARRAY_START);
                    return true;

                case ']':
                    $this->stream->readCharsToPeek();
                    $this->popStructToken(self::ARRAY_END);
                    return true;

                case '-':
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
                    $this->readNumber();
                    return true;

                case 't':
                case 'T':
                    $this->readTrue();
                    return true;

                case 'f':
                case 'F':
                    $this->readFalse();
                    return true;

                case 'n':
                case 'N':
                    $this->readNull();
                    return true;

                case "\r":
                case "\n":
                case "\t":
                case ' ':
                case ':':
                case ',':
                    $this->stream->readCharsToPeek();
                    break;

                default:
                    throw new RuntimeException(sprintf(
                        'Line %s, column %s: Unexpected %s',
                        $this->stream->getPeekLineNumber(),
                        $this->stream->getPeekColumnNumber(),
                        $peek
                    ));
            }
        }

        return false;
    }

    private function readTrue()
    {
        $string = '';
        for ($i = 0; $i < 4; ++$i) {
            $string .= $this->stream->readChar();
        }

        if (strtolower($string) != 'true') {
            throw new RuntimeException(sprintf(
                'Line %s, column %s: Expected true, got %s',
                $this->stream->getLineNumber(),
                $this->stream->getColumnNumber(),
                $string
            ));
        }

        $this->setTokenData(self::TRUE);
    }

    private function readFalse()
    {
        $string = '';
        for ($i = 0; $i < 5; ++$i) {
            $string .= $this->stream->readChar();
        }

        if (strtolower($string) != 'false') {
            throw new RuntimeException(sprintf(
                'Line %s, column %s: Expected false, got %s',
                $this->stream->getLineNumber(),
                $this->stream->getColumnNumber(),
                $string
            ));
        }

        $this->setTokenData(self::FALSE);
    }

    private function readNull()
    {
        $string = '';
        for ($i = 0; $i < 4; ++$i) {
            $string .= $this->stream->readChar();
        }

        if (strtolower($string) != 'null') {
            throw new RuntimeException(sprintf(
                'Line %s, column %s: Expected null, got %s',
                $this->stream->getLineNumber(),
                $this->stream->getColumnNumber(),
                $string
            ));
        }

        $this->setTokenData(self::NULL);
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
    private function readNumber()
    {
        $number = $this->stream->readCharsToPeek();

        while (!$this->stream->isEmpty()) {
            $peek = $this->stream->peek();

            switch ($peek) {
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
                    $number .= $this->stream->readCharsToPeek();
                    break;

                case '.':
                    $this->readFraction($number . $this->stream->readCharsToPeek());
                    return;

                case 'e':
                case 'E':
                    $this->readExponent($number);
                    return;

                case ' ':
                case "\r":
                case "\n":
                case "\t":
                    $this->stream->readCharsToPeek();
                    break;

                case ',':
                case '}':
                case ']':
                    $this->setTokenData(self::INT, $number);
                    return;

                default:
                    throw new RuntimeException(sprintf(
                        'Line %s, column %s: Unexpected %s',
                        $this->stream->getPeekLineNumber(),
                        $this->stream->getPeekColumnNumber(),
                        $peek
                    ));
            }
        }

        throw new RuntimeException(sprintf(
            'Line %s, column %s: Unexpected end of file, expected number',
            $this->stream->getPeekLineNumber(),
            $this->stream->getPeekColumnNumber()
        ));
    }

    /**
     *
     */
    private function readFraction($number)
    {
        while (!$this->stream->isEmpty()) {
            $peek = $this->stream->peek();

            switch ($peek) {
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
                    $number .= $this->stream->readCharsToPeek();
                    break;

                case 'e':
                case 'E':
                    $this->readExponent($number . $this->stream->readCharsToPeek());
                    return;

                case ' ':
                case "\n":
                case "\r":
                case "\t":
                    $this->stream->readCharsToPeek();
                    break;

                case ',':
                case ']':
                case '}':
                    $this->setTokenData(self::FLOAT, $number);
                    return;

                default:
                    throw new RuntimeException(sprintf(
                        'Line %s, column %s: Unexpected %s',
                        $this->stream->getPeekLineNumber(),
                        $this->stream->getPeekColumnNumber(),
                        $peek
                    ));
            }
        }

        throw new RuntimeException(sprintf(
            'Unexpected end of file, expected fraction part',
            $this->stream->getPeekLineNumber(),
            $this->stream->getPeekColumnNumber()
        ));
    }

    /**
     *
     */
    public function readExponent($number)
    {
        while (!$this->stream->isEmpty()) {
            $peek = $this->stream->peek();

            switch ($peek) {
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
                case '+':
                case '-':
                    $number .= $this->stream->readCharsToPeek();
                    break;

                case ' ':
                case "\n":
                case "\r":
                case "\t":
                    $this->stream->readCharsToPeek();
                    break;

                case ',':
                case ']':
                case '}':
                    $this->setTokenData(self::FLOAT, $number);
                    return;

                default:
                    throw new RuntimeException(sprintf(
                        'Expected exponent, got %s',
                        $peek
                    ));
            }
        }

        throw new RuntimeException('Expected exponent, got end of file');
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
                    'Line %s, column %s: Unexpected %s, expected valid escape sequence',
                    $this->stream->getLineNumber(),
                    $this->stream->getColumnNumber(),
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
                        'Line %s, column %s: Unexpected %s, expected hexadecimal character',
                        $this->stream->getLineNumber(),
                        $this->stream->getColumnNumber(),
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
            $char = $this->stream->peek();

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
                        'Line %s, column %s: Unexpected %s, expected : or ,',
                        $this->stream->getPeekLineNumber(),
                        $this->stream->getPeekColumnNumber(),
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

        $this->setTokenData($token);
    }

    /**
     * @param string $token
     */
    private function popStructToken($token)
    {
        $topToken = $this->structStack
            ->pop();

        switch ($token) {
            case self::ARRAY_END:
                if ($topToken == self::OBJECT) {
                    throw new RuntimeException(sprintf(
                        'Line %s, column %s: Expected ], got }',
                        $this->stream->getLineNumber(),
                        $this->stream->getColumnNumber()
                    ));
                }
                break;

            case self::OBJECT_END:
                if ($topToken == self::ARR) {
                    throw new RuntimeException(sprintf(
                        'Line %s, column %s: Expected }, got ]',
                        $this->stream->getLineNumber(),
                        $this->stream->getColumnNumber()
                    ));
                }
                break;
        }

        $this->setTokenData($token);
    }

}