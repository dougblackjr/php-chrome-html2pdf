<?php
/**
 * This file is part of the spiritix/php-chrome-html2pdf package.
 *
 * @copyright Copyright (c) Matthias Isler <mi@matthias-isler.ch>
 * @license   MIT
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Spiritix\Html2Pdf;

use Spiritix\Html2Pdf\Input\InputInterface;
use Spiritix\Html2Pdf\Output\OutputInterface;

/**
 * The actual HTML to PDF converter.
 *
 * @package Spiritix\Html2Pdf
 * @author  Matthias Isler <mi@matthias-isler.ch>
 */
class Converter
{
    /**
     * Name of the JS binary.
     */
    const JS_BINARY = 'index';

    /**
     * Input instance.
     *
     * @var InputInterface
     */
    private $input;

    /**
     * Output instance.
     *
     * @var OutputInterface
     */
    private $output;

    /**
     * The currently set options.
     *
     * @var array
     */
    private $options = [];

    /**
     * Initialize converter.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param array           $options Shortcut for setting multiple options
     */
    public function __construct(InputInterface $input, OutputInterface $output, array $options = [])
    {
        $this->input = $input;
        $this->output = $output;

        if (!empty($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * Returns all options.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Returns a single option.
     *
     * @param string $key Option key
     *
     * @throws ConverterException If option has not yet been set
     *
     * @return mixed Option value
     */
    public function getOption(string $key)
    {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }

        throw new ConverterException('Option "' . $key . '" has not been set');
    }

    /**
     * Sets a single option.
     *
     * @param string $key   Option key
     * @param mixed  $value Option value
     *
     * @throws ConverterException If option key is empty
     *
     * @return Converter
     */
    public function setOption(string $key, $value): Converter
    {
        if (empty($key)) {
            throw new ConverterException('Option key must not be empty');
        }

        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Set multiple options at once.
     *
     * @param array $options Multiple options
     *
     * @throws ConverterException If options are empty
     *
     * @return Converter
     */
    public function setOptions(array $options): Converter
    {
        if (empty($options)) {
            throw new ConverterException('Provided options must not be empty');
        }

        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }

        return $this;
    }

    /**
     * Runs the conversion.
     *
     * @throws ConverterException If a binary error occurred
     * @throws ConverterException If a shell error occurred
     * @throws ConverterException If no data was returned
     *
     * @return OutputInterface
     */
    public function convert(): OutputInterface
    {
        $result = $this->executeShellCommand($this->buildCommand(), $this->input->getHtml());

        if (strpos(mb_strtolower($result['error']), 'error') !== false) {
            throw new ConverterException('Binary error: ' . $result['error']);
        }

        if ($result['result'] > 1) {
            throw new ConverterException('Shell error: ' . $result['result']);
        }

        if (mb_strlen($result['output']) === 0) {
            throw new ConverterException('No data returned');
        }

        $this->output->setPdfData($result['output']);

        return $this->output;
    }

    /**
     * Builds the shell command for calling the binary.
     *
     * @return string
     */
    private function buildCommand(): string
    {
        $options = escapeshellarg(json_encode($this->getOptions()));
        $command = $this->getBinaryPath() . ' -o ' . $options;

        return $command;
    }

    /**
     * Executes a shell command.
     *
     * @param string $command The command to be executed
     * @param string $input   The data to be provided through the input stream
     *
     * @return array
     */
    protected function executeShellCommand(string $command, string $input = ''): array
    {
        $result = [];

        $proc = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        $result['output'] = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $result['error'] = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $result['result'] = (int) proc_close($proc);

        return $result;
    }

    /**
     * Returns the absolute path to the binary.
     *
     * @return string
     */
    private function getBinaryPath(): string
    {
        return 'node ' . dirname(__FILE__) . '/../../../' . self::JS_BINARY;
    }
}