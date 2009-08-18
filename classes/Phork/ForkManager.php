<?php

/**
 * ForkManager is a simple way to process tasks in parallel child processes
 * @author dennis@99designs.com
 */
class Phork_ForkManager
{
	private $_resources;
	private $_tasks;
	private $_callback;
	private $_limit;

	/**
	 * Each child gets allocated exclusive access to a resource in
	 * this list.
	 * @example List of test databases.
	 */
	public function resources($resources)
	{
		$this->_resources = $resources;
		return $this;
	}

	/**
	 * The array of tasks to process
	 */
	public function tasks($tasks)
	{
		$this->_tasks = $tasks;
		return $this;
	}

	/**
	 * A callback which accepts two arguments, a resource from the resource
	 * list and a task from the task list
	 */
	public function callback($callback)
	{
		$this->_callback = $callback;
		return $this;
	}

	/**
	 * Sets a limit to the maximum number of parallel child processes
	 * at one time
	 */
	public function limit($limit)
	{
		$this->_limit = $limit;
		return $this;
	}

	/**
	 * Business time! Starts processing.
	 */
	public function start()
	{
		while (count($this->_tasks) > 0)
		{
			$resource = isset($this->_resources)
				? array_shift($this->_resources)
				: null;

			$task = array_shift($this->_tasks);

			if (-1 == ($pid = pcntl_fork()))
			{
				throw new Exception('Could not fork');
			}
			else if ($pid == 0) // child process
			{
				call_user_func($this->_callback, $resource, $task);
				die();
			}
			else // parent process keeps track of resource usage
			{
				$procs[$pid] = $resource;
			}

			if ((isset($this->_limit) && count($procs) >= $this->_limit)
				|| (isset($this->_resources) && count($this->_resources) == 0))
			{
				if (-1 == ($pid = pcntl_wait($status)))
				{
					throw new Exception('Something went wrong in pcntl_wait');
				}

				$exitStatus = pcntl_wexitstatus($status);

				// make resource available again
				if (isset($this->_resources))
				{
					$resource = $procs[$pid];
					array_push($this->_resources, $resource);
				}

				unset($procs[$pid]);
			}
		}

		// Wait for remaining processes to finish
		foreach ($procs as $pid => $db)
		{
			pcntl_waitpid($pid, $status);
			$exitStatus = pcntl_wexitstatus($status);
		}
	}

}

