<?php

/**
 * @package    meta-palettes
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @copyright  2017 netzmacht David Molineus. All rights reserved.
 * @filesource
 *
 */

namespace ContaoCommunityAlliance\MetaPalettes\Parser;

use ContaoCommunityAlliance\MetaPalettes\Parser\MetaPalette\Interpreter;

/**
 * Class MetaPaletteParser
 *
 * @package ContaoCommunityAlliance\MetaPalettes\Parser
 */
class MetaPaletteParser
{
    const POSITION_AFTER = 'after';
    const POSITION_BEFORE = 'before';

    const MODE_ADD = 'add';
    const MODE_REMOVE = 'remove';
    const MODE_OVERRIDE = 'override';

    /**
     * Current palettes.
     *
     * @var array
     */
    private $palettes;

    /**
     * Parse a meta palettes definition.
     *
     * @param string      $tableName   Name of the data container table.
     * @param Interpreter $interpreter Interpreter which converts the definition.
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function parse($tableName, Interpreter $interpreter)
    {
        if (!isset($GLOBALS['TL_DCA'][$tableName]['metapalettes'])) {
            return false;
        }

        $this->preparePalettes($tableName);

        foreach (array_keys($this->palettes[$tableName]) as $palette) {
            $this->parsePalette($tableName, $palette, $interpreter);
        }

        $interpreter->finish();
        $this->palettes = [];

        return true;
    }

    /**
     * Parse a palette.
     *
     * @param string      $tableName   Name of the data container table.
     * @param string      $paletteName Table name.
     * @param Interpreter $interpreter Interpreter which converts the definition.
     * @param bool        $parent      If true palette is parsed as a parent palette.
     *
     * @return void
     */
    public function parsePalette($tableName, $paletteName, Interpreter $interpreter, $parent = false)
    {
        // Check if palettes for the table are prepared.
        if (!isset($this->palettes[$tableName])) {
            $this->preparePalettes($tableName);
        }

        if (!isset($this->palettes[$tableName][$paletteName])) {
            throw new \InvalidArgumentException(
                sprintf('Metapalette definition of palette "%s" does not exist', $paletteName)
            );
        }

        if (!$parent) {
            $interpreter->start($tableName, $paletteName);
        }

        foreach ($this->palettes[$tableName][$paletteName]['parents'] as $parent) {
            $interpreter->inherit($parent, $this);
        }

        foreach ($this->palettes[$tableName][$paletteName]['definition'] as $legend => $fields) {
            $this->parseLegend($legend, $fields, $parent, $interpreter);
        }
    }

    /**
     * Prepare the palettes.
     *
     * @param string $tableName Table name.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private function preparePalettes($tableName)
    {
        $this->palettes[$tableName] = [];

        foreach ($GLOBALS['TL_DCA'][$tableName]['metapalettes'] as $paletteName => $definition) {
            $parents = $this->extractParents($paletteName);

            $this->palettes[$tableName][$paletteName] = [
                'definition' => $definition,
                'parents'    => $parents
            ];
        }
    }

    /**
     * Extract the parents from palette name and set referenced palette name to the new value.
     *
     * @param string $paletteName Palette name.
     *
     * @return array
     */
    private function extractParents(&$paletteName)
    {
        $parents     = explode(' extends ', $paletteName);
        $paletteName = array_shift($parents);

        return array_reverse($parents);
    }

    /**
     * Parse a legend.
     *
     * @param string      $legend      Raw name of the legend. Can contain the insert mode as first character.
     * @param array       $fields      List of fields.
     * @param bool        $parent      If true palette is parsed as a parent palette.
     * @param Interpreter $interpreter The parser interpreter.
     */
    private function parseLegend($legend, array $fields, $parent, Interpreter $interpreter)
    {
        $hide   = in_array(':hide', $fields);
        $fields = array_filter(
            $fields,
            function ($strField) {
                return $strField[0] != ':';
            }
        );

        $mode     = $this->extractInsertMode($legend, static::MODE_OVERRIDE);
        $override = !$parent || $mode === static::MODE_OVERRIDE;

        if (!$override && !$hide) {
            $hide = null;
        }

        $interpreter->addLegend($legend, $override, $hide);

        foreach ($fields as $field) {
            $fieldMode = $this->extractInsertMode($field, $mode);

            if ($fieldMode === self::MODE_REMOVE) {
                $interpreter->removeFieldFrom($legend, $field);
                continue;
            }

            if (preg_match('#^(\w+) (before|after) (\w+)$#', $field, $matches)) {
                $interpreter->addFieldTo($legend, $matches[1], $matches[2], $matches[3]);
            } else {
                $interpreter->addFieldTo($legend, $field);
            }
        }
    }

    /**
     * Extract insert mode from a name.
     *
     * @param string $name    Name passed as reference.
     * @param string $default Default insert mode.
     *
     * @return string
     */
    private function extractInsertMode(&$name, $default = self::MODE_ADD)
    {
        switch ($name[0]) {
            case '+':
                $mode = self::MODE_ADD;
                break;

            case '-':
                $mode = self::MODE_REMOVE;
                break;

            default:
                return $default;
        }

        $name = substr($name, 1);

        return $mode;
    }
}