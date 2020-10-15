<?php

namespace Icinga\Module\Vspheredb;

use Error;
use Exception;
use gipfl\Cli\Screen;
use InvalidArgumentException;

trait CheckPluginHelper
{
    /** @var int */
    protected $state = 0;

    protected $sortingState;

    protected $sortingStateMap = [0, 1, 3, 2];

    protected $outputScreen;

    /** @var array */
    protected $nameStateMap = [
        'OK'       => 0,
        'WARNING'  => 1,
        'CRITICAL' => 2,
        'UNKNOWN'  => 3,
    ];

    /** @var array */
    protected $stateNameMap = [
        'OK',
        'WARNING',
        'CRITICAL',
        'UNKNOWN',
    ];

    protected $stateColors = [
        'OK'       => 'green',
        'WARNING'  => 'brown',
        'CRITICAL' => 'red',
        'UNKNOWN'  => 'purple',
    ];

    /** @var array */
    protected $messages = [];

    /** @var string|null */
    protected $message;

    /**
     * @param $callable
     */
    protected function run($callable)
    {
        if (\is_callable($callable)) {
            try {
                $callable();
            } catch (Exception $e) {
                $this->addProblem('UNKNOWN', $this->stripNonUtf8Characters($e->getMessage()));
            } catch (Error $e) {
                $this->addProblem('UNKNOWN', $this->stripNonUtf8Characters($e->getMessage()));
            }
        } else {
            $this->addProblem('UNKNOWN', 'CheckPluginHelper requires a "callable"');
        }

        $this->shutdown();
    }

    /**
     * @param string $string
     * @return string
     */
    protected function stripNonUtf8Characters($string)
    {
        return iconv('UTF-8', 'UTF-8//IGNORE', $string);
    }

    /**
     * @param null $state
     * @return mixed
     */
    protected function getStateName($state = null)
    {
        if ($state === null) {
            return $this->stateNameMap[$this->state];
        } else {
            return $this->stateNameMap[$this->wantNumericState($state)];
        }
    }

    /**
     * @param int|string $state
     * @param string $message
     * @return $this
     */
    protected function addProblem($state, $message)
    {
        $this->raiseState($state);
        $stateName = $this->getStateName($state);
        $this->addMessage(sprintf(
            '%s %s',
            $this->getOutputScreen()->colorize("[$stateName]", $this->stateColors[$stateName]),
            $message
        ));

        return $this;
    }

    protected function getOutputScreen()
    {
        if ($this->outputScreen === null) {
            $this->outputScreen = Screen::factory();
        }

        return $this->outputScreen;
    }

    /**
     * @param string $message
     * @return $this
     */
    protected function addMessage($message)
    {
        $this->messages[] = $message;

        return $this;
    }

    /**
     * @param string $message
     * @return $this
     */
    protected function prependMessage($message)
    {
        array_unshift($this->messages, $message);

        return $this;
    }

    /**
     * @param int|string $state
     * @return $this
     */
    protected function raiseState($state)
    {
        $state = $this->wantNumericState($state);
        if ($this->sortingStateMap[$state] > $this->sortingStateMap[$this->state]) {
            $this->state = $state;
        }

        return $this;
    }

    /**
     * @return int
     */
    protected function getState()
    {
        return $this->state;
    }

    /**
     * @param $state
     * @return int
     */
    protected function wantNumericState($state)
    {
        if (is_int($state) || ctype_digit($state)) {
            if (array_key_exists($state, $this->stateNameMap)) {
                return (int) $state;
            } else {
                throw new InvalidArgumentException(sprintf('%d is not a valid numeric state', $state));
            }
        } else {
            if (array_key_exists($state, $this->nameStateMap)) {
                return $this->nameStateMap[$state];
            } else {
                throw new InvalidArgumentException(sprintf('%s is not a valid state name', $state));
            }
        }
    }

    protected function getMessages()
    {
        return $this->messages;
    }

    protected function shutdown()
    {
        echo implode("\n", $this->getMessages()) . "\n";
        exit($this->getState());
    }
}
