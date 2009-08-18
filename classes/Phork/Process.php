<?php

/**
 * An asynchronous process that can process messages sent to it.
 * @author dennis@99designs.com
 */
class Phork_Process
{
	const NORMAL = 1; // normal message type
	const ENDPROCESS = 2; // message to end process gracefully

	const RESULT_NORMAL = 1; // normal child result type
	const RESULT_SHUTDOWN = 2; // child exit result type

	const MAX_SIZE = 8192; // max size of serialized message

	private $_pid;
	private $_uid;
	private $_child = false;

	/**
	 * @param $uid A unique id for this process
	 * @param $callback A callback to process incoming messages
	 */
	public function __construct($uid, $callback)
	{
		$this->_uid = $uid;

		$queue = msg_get_queue($this->_uid);

		// Fork a process that receieves messages
		if (-1 == ($this->_pid = pcntl_fork()))
		{
			throw new Exception('Error, could not fork');
		}
		elseif ($this->_pid == 0) // child
		{
			register_shutdown_function(array($this, 'childShutdownCallback'));
			$this->_child = true;

			do
			{
				if (!msg_receive($queue, 0, $type, self::MAX_SIZE, $msg))
					break;

				if ($type != self::ENDPROCESS)
				{
					$result = call_user_func($callback, $msg);

					// Send result to result queue
					$resultQueue = msg_get_queue($this->_uid + 4096);
					msg_send($resultQueue, self::RESULT_NORMAL, $result);
				}
			} while ($type != self::ENDPROCESS);
			die();
		}
	}

	/**
	 * Send a message to the process
	 */
	public function send($msg)
	{
		$queue = msg_get_queue($this->_uid);
		msg_send($queue, self::NORMAL, $msg);
	}

	/**
	 * Get the result of a previous send call.
	 */
	public function result()
	{
		$resultQueue = msg_get_queue($this->_uid + 4096);

		if (!msg_receive($resultQueue, 0, $type, self::MAX_SIZE, $msg))
			throw new Exception('Something went wrong in msg_receive');

		if ($type == self::RESULT_SHUTDOWN)
			throw new Exception('child exited');

		return $msg;
	}

	public function end()
	{
		$queue = msg_get_queue($this->_uid);
		$resultQueue = msg_get_queue($this->_uid + 4096);

		msg_send($queue, self::ENDPROCESS, '');
		pcntl_waitpid($this->_pid, $status);
		msg_remove_queue($queue);
		msg_remove_queue($resultQueue);
	}

	public function __destruct()
	{
		if (!$this->_child)
		{
			$this->end();
		}
	}

	/**
	 * Child sends shutdown message to result queue
	 */
	public function childShutdownCallback()
	{
		// Send result to result queue
		$resultQueue = msg_get_queue($this->_uid + 4096);
		msg_send($resultQueue, self::RESULT_SHUTDOWN, null);
	}
}

