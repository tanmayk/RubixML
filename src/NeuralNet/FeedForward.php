<?php

namespace Rubix\ML\NeuralNet;

use Tensor\Matrix;
use Rubix\ML\Encoding;
use Rubix\ML\Datasets\Dataset;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\NeuralNet\Layers\Input;
use Rubix\ML\NeuralNet\Layers\Output;
use Rubix\ML\NeuralNet\Layers\Parametric;
use Rubix\ML\NeuralNet\Optimizers\Adaptive;
use Rubix\ML\NeuralNet\Optimizers\Optimizer;
use Traversable;

use function array_reverse;

/**
 * Feed Forward
 *
 * A feed forward neural network implementation consisting of an input and
 * output layer and any number of intermediate hidden layers.
 *
 * @internal
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class FeedForward implements Network
{
    /**
     * The input layer to the network.
     *
     * @var \Rubix\ML\NeuralNet\Layers\Input
     */
    protected \Rubix\ML\NeuralNet\Layers\Input $input;

    /**
     * The hidden layers of the network.
     *
     * @var list<\Rubix\ML\NeuralNet\Layers\Hidden>
     */
    protected array $hidden = [
        //
    ];

    /**
     * The pathing of the backward pass through the hidden layers.
     *
     * @var list<\Rubix\ML\NeuralNet\Layers\Hidden>
     */
    protected array $backPass = [
        //
    ];

    /**
     * The output layer of the network.
     *
     * @var \Rubix\ML\NeuralNet\Layers\Output
     */
    protected \Rubix\ML\NeuralNet\Layers\Output $output;

    /**
     * The gradient descent optimizer used to train the network.
     *
     * @var \Rubix\ML\NeuralNet\Optimizers\Optimizer
     */
    protected \Rubix\ML\NeuralNet\Optimizers\Optimizer $optimizer;

    /**
     * @param \Rubix\ML\NeuralNet\Layers\Input $input
     * @param \Rubix\ML\NeuralNet\Layers\Hidden[] $hidden
     * @param \Rubix\ML\NeuralNet\Layers\Output $output
     * @param \Rubix\ML\NeuralNet\Optimizers\Optimizer $optimizer
     */
    public function __construct(Input $input, array $hidden, Output $output, Optimizer $optimizer)
    {
        $hidden = array_values($hidden);

        $backPass = array_reverse($hidden);

        $this->input = $input;
        $this->hidden = $hidden;
        $this->output = $output;
        $this->optimizer = $optimizer;
        $this->backPass = $backPass;
    }

    /**
     * Return the input layer.
     *
     * @return \Rubix\ML\NeuralNet\Layers\Input
     */
    public function input() : Input
    {
        return $this->input;
    }

    /**
     * Return an array of hidden layers indexed left to right.
     *
     * @return list<\Rubix\ML\NeuralNet\Layers\Hidden>
     */
    public function hidden() : array
    {
        return $this->hidden;
    }

    /**
     * Return the output layer.
     *
     * @return \Rubix\ML\NeuralNet\Layers\Output
     */
    public function output() : Output
    {
        return $this->output;
    }

    /**
     * Return all the layers in the network.
     *
     * @return \Traversable<\Rubix\ML\NeuralNet\Layers\Layer>
     */
    public function layers() : Traversable
    {
        yield $this->input;

        yield from $this->hidden;

        yield $this->output;
    }

    /**
     * Initialize the parameters of the layers and warm the optimizer cache.
     */
    public function initialize() : void
    {
        $fanIn = 1;

        foreach ($this->layers() as $layer) {
            $fanIn = $layer->initialize($fanIn);
        }

        if ($this->optimizer instanceof Adaptive) {
            foreach ($this->layers() as $layer) {
                if ($layer instanceof Parametric) {
                    foreach ($layer->parameters() as $param) {
                        $this->optimizer->warm($param);
                    }
                }
            }
        }
    }

    /**
     * Run an inference pass and return the activations at the output layer.
     *
     * @param \Rubix\ML\Datasets\Dataset $dataset
     * @return \Tensor\Matrix
     */
    public function infer(Dataset $dataset) : Matrix
    {
        $input = Matrix::quick($dataset->samples())->transpose();

        foreach ($this->layers() as $layer) {
            $input = $layer->infer($input);
        }

        return $input->transpose();
    }

    /**
     * Perform a forward and backward pass of the network in one call. Returns
     * the loss from the backward pass.
     *
     * @param \Rubix\ML\Datasets\Labeled $dataset
     * @return float
     */
    public function roundtrip(Labeled $dataset) : float
    {
        $input = Matrix::quick($dataset->samples())->transpose();

        $this->feed($input);

        $loss = $this->backpropagate($dataset->labels());

        return $loss;
    }

    /**
     * Feed a batch through the network and return a matrix of activations at the output later.
     *
     * @param \Tensor\Matrix $input
     * @return \Tensor\Matrix
     */
    public function feed(Matrix $input) : Matrix
    {
        foreach ($this->layers() as $layer) {
            $input = $layer->forward($input);
        }

        return $input;
    }

    /**
     * Backpropagate the gradient of the cost function and return the loss.
     *
     * @param list<string|int|float> $labels
     * @return float
     */
    public function backpropagate(array $labels) : float
    {
        [$gradient, $loss] = $this->output->back($labels, $this->optimizer);

        foreach ($this->backPass as $layer) {
            $gradient = $layer->back($gradient, $this->optimizer);
        }

        return $loss;
    }

    /**
     * Export the network architecture as a graph in dot format.
     * 
     * @return \Rubix\ML\Encoding
     */
    public function exportGraphviz() : Encoding
    {
        $dot = 'digraph Tree {' . PHP_EOL;
        $dot .= '  node [shape=box, fontname=helvetica];' . PHP_EOL;

        $layerNum = 0;

        foreach ($this->layers() as $layer) {
            ++$layerNum;

            $dot .= "  N$layerNum [label=\"$layer\",style=\"rounded\"]" . PHP_EOL;

            if ($layerNum > 1) {
                $parentId = $layerNum - 1;

                $dot .= "  N{$parentId} -> N{$layerNum};" . PHP_EOL;
            }
        }

        $dot .= '}';

        return new Encoding($dot);
    }
}
