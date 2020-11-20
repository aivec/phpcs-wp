<?php

namespace AivecCS\Core\Sniffs;

use WordPressCS\WordPress\Sniffs\Arrays\ArrayDeclarationSpacingSniff;
use PHP_CodeSniffer\Util\Tokens;

class ArrayBracketSpacingSniff extends ArrayDeclarationSpacingSniff
{
    /**
     * Process a single-line array.
     *
     * @since 0.13.0 The actual checks contained in this method used to
     *               be in the `process()` method.
     *
     * @param int $stackPtr The position of the current token in the stack.
     * @param int $opener   The position of the array opener.
     * @param int $closer   The position of the array closer.
     *
     * @return void
     */
    protected function process_single_line_array($stackPtr, $opener, $closer)
    {
        /*
         * Check that associative arrays are always multi-line.
         */
        $array_has_keys = $this->phpcsFile->findNext(\T_DOUBLE_ARROW, $opener, $closer);
        if (false !== $array_has_keys) {
            $array_items = $this->get_function_call_parameters($stackPtr);

            if (
                (false === $this->allow_single_item_single_line_associative_arrays
                    && !empty($array_items))
                || (true === $this->allow_single_item_single_line_associative_arrays
                    && \count($array_items) > 1)
            ) {
                /*
                 * Make sure the double arrow is for *this* array, not for a nested one.
                 */
                $array_has_keys = false; // Reset before doing more detailed check.
                foreach ($array_items as $item) {
                    for ($ptr = $item['start']; $ptr <= $item['end']; $ptr++) {
                        if (\T_DOUBLE_ARROW === $this->tokens[$ptr]['code']) {
                            $array_has_keys = true;
                            break 2;
                        }

                        // Skip passed any nested arrays.
                        if (isset($this->targets[$this->tokens[$ptr]['code']])) {
                            $nested_array_open_close = $this->find_array_open_close($ptr);
                            if (false === $nested_array_open_close) {
                                // Nested array open/close could not be determined.
                                continue;
                            }

                            $ptr = $nested_array_open_close['closer'];
                        }
                    }
                }

                if (true === $array_has_keys) {
                    $phrase = 'an';
                    if (true === $this->allow_single_item_single_line_associative_arrays) {
                        $phrase = 'a multi-item';
                    }
                    $fix = $this->phpcsFile->addFixableError(
                        'When %s array uses associative keys, each value should start on a new line.',
                        $closer,
                        'AssociativeArrayFound',
                        [$phrase]
                    );

                    if (true === $fix) {
                        $this->phpcsFile->fixer->beginChangeset();

                        foreach ($array_items as $item) {
                            /*
                             * Add a line break before the first non-empty token in the array item.
                             * Prevents extraneous whitespace at the start of the line which could be
                             * interpreted as alignment whitespace.
                             */
                            $first_non_empty = $this->phpcsFile->findNext(
                                Tokens::$emptyTokens,
                                $item['start'],
                                ($item['end'] + 1),
                                true
                            );
                            if (false === $first_non_empty) {
                                continue;
                            }

                            if (
                                $item['start'] <= ($first_non_empty - 1)
                                && \T_WHITESPACE === $this->tokens[($first_non_empty - 1)]['code']
                            ) {
                                // Remove whitespace which would otherwise becoming trailing
                                // (as it gives problems with the fixed file).
                                $this->phpcsFile->fixer->replaceToken(($first_non_empty - 1), '');
                            }

                            $this->phpcsFile->fixer->addNewlineBefore($first_non_empty);
                        }

                        $this->phpcsFile->fixer->endChangeset();
                    }

                    // No need to check for spacing around opener/closer as this array should be multi-line.
                    return;
                }
            }
        }

        // Square brackets can not have a space before them.
        $prevType = $this->tokens[($closer - 1)]['code'];
        if ($prevType === T_WHITESPACE) {
            $nonSpace = $this->phpcsFile->findPrevious(T_WHITESPACE, ($closer - 2), null, true);
            $expected = $this->tokens[$nonSpace]['content'] . $this->tokens[$closer]['content'];
            $found = $this->phpcsFile->getTokensAsString($nonSpace, ($closer - $nonSpace)) . $this->tokens[$closer]['content'];
            $error = 'Space found before square bracket; expected "%s" but found "%s"';
            $data = [
                $expected,
                $found,
            ];
            $fix = $this->phpcsFile->addFixableError($error, $closer, 'SpaceBeforeBracket', $data);
            if ($fix === true) {
                $this->phpcsFile->fixer->replaceToken(($closer - 1), '');
            }
        }

        // Open square brackets can't ever have spaces after them.
        $nextType = $this->tokens[($opener + 1)]['code'];
        if ($nextType === T_WHITESPACE) {
            $nonSpace = $this->phpcsFile->findNext(T_WHITESPACE, ($opener + 2), null, true);
            $expected = $this->tokens[$opener]['content'] . $this->tokens[$nonSpace]['content'];
            $found = $this->phpcsFile->getTokensAsString($opener, ($nonSpace - $opener + 1));
            $error = 'Space found after square bracket; expected "%s" but found "%s"';
            $data = [
                $expected,
                $found,
            ];
            $fix = $this->phpcsFile->addFixableError($error, $opener, 'SpaceAfterBracket', $data);
            if ($fix === true) {
                $this->phpcsFile->fixer->replaceToken(($opener + 1), '');
            }
        }
    }
}
