<?php

namespace Rubix\ML\Other\Strategies;

use InvalidArgumentException;
use RuntimeException;

/**
 * Lottery
 *
 * Hold a lottery in which each category has an equal chance of being picked.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class Lottery implements Categorical
{
    /**
     * The unique outcomes each having equal chance of winning lottery.
     *
     * @var array
     */
    protected $categories = [
        //
    ];

    /**
     * Store every unique outcome.
     *
     * @param  array  $values
     * @throws \RuntimeException
     * @return void
     */
    public function fit(array $values) : void
    {
        if (empty($values)) {
            throw new InvalidArgumentException('Strategy needs to be fit with'
                . ' at least one value.');
        }

        $this->categories = array_values(array_unique($values));
    }

    /**
     * Hold a lottery in which each category has an equal chance of being picked.
     *
     * @return mixed
     */
    public function guess()
    {
        if (empty($this->categories)) {
            throw new RuntimeException('Strategy has not been fitted.');
        }

        return $this->categories[array_rand($this->categories)];
    }
}