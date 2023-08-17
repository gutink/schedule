<?php

namespace schedule\console;

use Carbon\Carbon;
use Closure;
use Cron\CronExpression;
use think\Container;

class Event
{
    use ManagesFrequencies;

    public $command;

    protected $parameters;

    public $timezone;

    public $expression = '* * * * *';

    protected $filters = [];

    protected $rejects = [];

    protected $beforeCallbacks = [];

    protected $afterCallbacks = [];

    public function __construct($command, array $parameters = [])
    {
        $this->command = $command;

        $this->parameters = $parameters;
    }

    public function run(Container $container)
    {
        $this->callBeforeCallbacks($container);

        if (\think\App::VERSION >= 6) {
            \think\facade\Console::call($this->command, $this->parameters, 'console');
        } else {
            \think\Console::call($this->command, $this->parameters, 'console');
        }


        $this->callAfterCallbacks($container);
    }

    /**
     * 过滤
     * @param $app
     * @return bool
     */
    public function filtersPass($app)
    {
        foreach ($this->filters as $callback) {
            if (!$app->call($callback)) {
                return false;
            }
        }
        foreach ($this->rejects as $callback) {
            if ($app->call($callback)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 是否到期执行
     * @param $app
     * @return bool
     */
    public function isDue($app)
    {
        return $this->expressionPasses();
    }

    public function expressionPasses()
    {
        $date = Carbon::now();

        if ($this->timezone) {
            $date->setTimezone($this->timezone);
        }

        return CronExpression::factory($this->expression)->isDue($date->toDateTimeString());
    }

    public function skip($callback)
    {
        $this->rejects[] = is_callable($callback) ? $callback : function () use ($callback) {
            return $callback;
        };;

        return $this;
    }

    public function when($callback)
    {
        $this->filters[] = is_callable($callback) ? $callback : function () use ($callback) {
            return $callback;
        };

        return $this;
    }

    public function before(Closure $callback)
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }


    public function after(Closure $callback)
    {
        return $this->then($callback);
    }

    public function then(Closure $callback)
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    public function callBeforeCallbacks(Container $container)
    {
        foreach ($this->beforeCallbacks as $callback) {
            $container->invokeFunction($callback);
        }
    }

    public function callAfterCallbacks(Container $container)
    {
        foreach ($this->afterCallbacks as $callback) {
            $container->invokeFunction($callback);
        }
    }


    public function timezone($timezone)
    {
        $this->timezone = $timezone;

        return $this;
    }
}
