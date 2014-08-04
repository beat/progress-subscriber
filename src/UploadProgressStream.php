<?php
namespace GuzzleHttp\Subscriber\Progress;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Stream\MetadataStreamInterface;
use GuzzleHttp\Stream\StreamInterface;

/**
 * Adds upload progress events to a stream.
 *
 * The supplied callable is invoked each time data is read from the stream.
 * The callable is provided the expected number of bytes to upload followed
 * by the total number of uploaded bytes.
 */
class UploadProgressStream implements StreamInterface
{
    //BB use StreamDecoratorTrait;
	/** @var StreamInterface Decorated stream */
	private $stream;

	/*BB
	 * @param StreamInterface $stream Stream to decorate
	 *
	public function __construct(StreamInterface $stream)
	{
		$this->stream = $stream;
	}
	*/

	public function __toString()
	{
		try {
			$this->seek(0);
			return $this->getContents();
		} catch (\Exception $e) {
			// Really, PHP? https://bugs.php.net/bug.php?id=53648
			trigger_error('StreamDecorator::__toString exception: '
				. (string) $e, E_USER_ERROR);
			return '';
		}
	}

	public function getContents($maxLength = -1)
	{
		return \GuzzleHttp\Stream\copy_to_string($this, $maxLength);
	}

	/**
	 * Allow decorators to implement custom methods
	 *
	 * @param string $method Missing method name
	 * @param array  $args   Method arguments
	 *
	 * @return mixed
	 */
	public function __call($method, array $args)
	{
		$result = call_user_func_array(array($this->stream, $method), $args);

		// Always return the wrapped object if the result is a return $this
		return $result === $this->stream ? $this : $result;
	}

	public function close()
	{
		return $this->stream->close();
	}

	public function getMetadata($key = null)
	{
		return $this->stream instanceof MetadataStreamInterface
			? $this->stream->getMetadata($key)
			: null;
	}

	public function detach()
	{
		$this->stream->detach();

		return $this;
	}

	public function getSize()
	{
		return $this->stream->getSize();
	}

	public function eof()
	{
		return $this->stream->eof();
	}

	public function tell()
	{
		return $this->stream->tell();
	}

	public function isReadable()
	{
		return $this->stream->isReadable();
	}

	public function isWritable()
	{
		return $this->stream->isWritable();
	}

	public function isSeekable()
	{
		return $this->stream->isSeekable();
	}

	public function seek($offset, $whence = SEEK_SET)
	{
		return $this->stream->seek($offset, $whence);
	}

	/*BB
	public function read($length)
	{
		return $this->stream->read($length);
	}
	*/

	public function write($string)
	{
		return $this->stream->write($string);
	}
    //BB end StreamDecoratorTrait;

    private $reachedEnd;
    private $client;
    private $request;

    /**
     * @param StreamInterface  $stream  Stream to wrap
     * @param callable         $notify  Function to invoke as data is read
     * @param ClientInterface  $client  Client sending the request
     * @param RequestInterface $request Request being sent
     */
    public function __construct(
        StreamInterface $stream,
        callable $notify,
        ClientInterface $client,
        RequestInterface $request
    ) {
        $this->stream = $stream;
        $this->notify = $notify;
        $this->client = $client;
        $this->request = $request;
    }

    public function read($length)
    {
        $result = $this->stream->read($length);

        if (!$result) {
            $this->reachedEnd = true;
        } elseif (!$this->reachedEnd) {
            call_user_func(
                $this->notify,
                $this->getSize(),
                $this->tell(),
                $this->client,
                $this->request
            );
        }

        return $result;
    }
}
