<?php

namespace Rubix\ML\Transformers;

use Rubix\ML\Datasets\Dataset;
use MathPHP\Statistics\Descriptive;
use RuntimeException;

/**
 * Quartile Standardizer
 *
 * This standardizer removes the median and scales each sample according to the
 * interquantile range (IQR). The IQR is the range between the 1st quartile
 * (25th quantile) and the 3rd quartile (75th quantile).
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class QuartileStandardizer implements Transformer
{
    /**
     * Should we center the data?
     *
     * @var bool
     */
    protected $center;

    /**
     * Should we scale the data?
     *
     * @var bool
     */
    protected $scale;

    /**
     * The computed medians of the fitted data indexed by column.
     *
     * @var array|null
     */
    protected $medians;

    /**
     * The computed interquartile ranges of the fitted data indexed by column.
     *
     * @var array|null
     */
    protected $iqrs;

    /**
     * @param  bool  $center
     * @param  bool  $scale
     * @return void
     */
    public function __construct(bool $center = true, bool $scale = true)
    {
        $this->center = $center;
        $this->scale = $scale;
    }

    /**
     * Return the means calculated by fitting the training set.
     *
     * @return array|null
     */
    public function medians() : ?array
    {
        return $this->medians;
    }

    /**
     * Return the interquartile ranges calculated during fitting.
     *
     * @return array|null
     */
    public function iqrs() : ?array
    {
        return $this->iqrs;
    }

    /**
     * Calculate the medians and interquartile ranges of the dataset.
     *
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @return void
     */
    public function fit(Dataset $dataset) : void
    {
        $this->medians = $this->iqrs = [];

        foreach ($dataset->rotate() as $column => $values) {
            if ($dataset->type($column) === Dataset::CONTINUOUS) {
                $quartiles = Descriptive::quartiles($values);

                $this->medians[$column] = $quartiles['Q2'];
                $this->iqrs[$column] = $quartiles['IQR'];
            }
        }
    }

    /**
     * Center and scale the features of the sample matrix.
     *
     * @param  array  $samples
     * @throws \RuntimeException
     * @return void
     */
    public function transform(array &$samples) : void
    {
        if (is_null($this->medians) or is_null($this->iqrs)) {
            throw new RuntimeException('Transformer has not been fitted.');
        }

        foreach ($samples as &$sample) {
            foreach ($this->medians as $column => $median) {
                $feature = $sample[$column];

                if ($this->center === true) {
                    $feature -= $median;
                }

                if ($this->scale === true) {
                    $iqr = $this->iqrs[$column];

                    $feature = $iqr !== 0.0 ? $feature / $iqr : 1.0;
                }

                $sample[$column] = $feature;
            }
        }
    }
}