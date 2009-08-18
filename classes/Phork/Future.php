<?php

/**
 * A Future represents the result of an asynchronous computation.
 * @author dennis@99designs.com
 */
class Phork_Future
{
	private $_process;
	private $_result;

	/**
	 * @param $uid A unique id for this process
	 * @param $callback A callback to process incoming messages
	 * @param $param The parameter to pass to the callback
	 */
	public function __construct($uid, $callback, $param)
	{
		$this->_process = new Phork_Process($uid, $callback);
		$this->_process->send($param);
	}

	/**
	 * Get the result of the computation
	 */
	public function get()
	{
		if ($this->_result)
			return $this->_result;

		$this->_result = $this->_process->result();
		$this->_process->end();
		return $this->_result;
	}
}

