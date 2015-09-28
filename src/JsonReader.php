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
        $this->stream->resetPeek();

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

                case 'T':
                    $this->readTrue();
                    return true;

                case 'F':
                    $this->readFalse();
                    return true;

                case 'N':
                    $this->readNull();
                    return true;

                case "\n":
                case "\t":
                case "\r":
                case ' ':
                case ':':
                case ',':
                    $this->stream->readCharsToPeek();
                    break;

                default:
                    throw new RuntimeException(sprintf(
                        'Unexpected %s',
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

        if ($string != 'TRUE') {
            throw new RuntimeException(sprintf(
                'Expected TRUE, got %s',
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

        if ($string != 'FALSE') {
            throw new RuntimeException(sprintf(
                'Expected FALSE, got %s',
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

        if ($string != 'NULL') {
            throw new RuntimeException(sprintf(
                'Expected NULL, got %s',
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
        $number = '';

        while (!$this->stream->isEmpty()) {
            $peek = $this->stream->peek();

            switch (strtolower($peek)) {
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
                    $this->readExponent($number);
                    return;

                case ',':
                case '}':
                case ']':
                    $this->setTokenData(self::INT, $number);
                    return;
                    break;

                default:
                    throw new RuntimeException(sprintf(
                        'Unexpected %s',
                        $peek
                    ));
            }
        }

        throw new RuntimeException('Unexpected end of file, expected number');
    }

    /**
     *
     */
    private function readFraction($number)
    {
        while (!$this->stream->isEmpty()) {
            $peek = $this->stream->peek();

            switch (strtolower($peek)) {
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
                    $this->readExponent($number . $this->stream->readCharsToPeek());
                    return;

                case ',':
                case ']':
                case '}':
                    $this->setTokenData(self::FLOAT, $number);
                    return;

                default:
                    throw new RuntimeException(sprintf(
                        'Unexpected %s',
                        $peek
                    ));
            }
        }

        throw new RuntimeException('Unexpected end of file, expected fraction part');
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
            ->pop();

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

        $this->setTokenData($token);

        if ($this->structStack->isEmpty()) {
            $this->currentStruct = null;
            $this->currentDepth = 0;
            return;
        }

        $this->currentStruct = $this->structStack
            ->top();

        $this->currentDepth = $this->structStack
            ->count();
    }

}