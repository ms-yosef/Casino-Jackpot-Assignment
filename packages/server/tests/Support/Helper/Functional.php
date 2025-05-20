<?php

namespace Tests\Support\Helper;

use Codeception\Module;

class Functional extends Module
{
    /**
     * Generates a unique test session ID.
     */
    public function generateTestSessionId(): string
    {
        return 'test_functional_' . uniqid('', true);
    }
    
    public function validateSpinResult(array $spinResult): void
    {
        $this->assertArrayHasKey('reels', $spinResult, 'Result must contain "reels" key. ');
        $this->assertArrayHasKey('betAmount', $spinResult, 'Result must contain "betAmount" key. ');
        $this->assertArrayHasKey('winAmount', $spinResult, 'Result must contain "winAmount" key. ');
        
        $this->assertIsArray($spinResult['reels'], 'Reels must be an array. ');
        
        $this->assertNotEmpty($spinResult['reels'], 'Reels must not be empty. ');
        
        $validSymbols = ['C', 'L', 'O', 'W'];
        
        codecept_debug('Reels structure: ' . json_encode($spinResult['reels']));
        
        foreach ($spinResult['reels'] as $item) {
            if (is_array($item)) {
                foreach ($item as $symbol) {
                    $this->assertContains($symbol, $validSymbols, "Symbol $symbol must be one of: " . implode(', ', $validSymbols));
                }
            } else {
                $this->assertContains($item, $validSymbols, "Symbol $item must be one of: " . implode(', ', $validSymbols));
            }
        }
    }
    
    /**
     * Checks if winAmount complies with the game rules:
     * - If winAmount > 0, all characters must be the same
     * - If the characters are different or not valid, winAmount must be 0
     */
    public function validateWinAmount(array $spinResult): void
    {
        $reels = $spinResult['reels'];
        $winAmount = $spinResult['winAmount'];
        
        $symbols = [];
        foreach ($reels as $item) {
            if (is_array($item)) {
                $symbols[] = $item[0];
            } else {
                $symbols[] = $item;
            }
        }
        
        $isAllSame = count(array_unique($symbols)) === 1;
        
        if ($winAmount > 0) {
            $this->assertTrue($isAllSame, 
                "For a winning combination (winAmount = $winAmount), all symbols must be the same, but received: " .
                implode('', $symbols));
        }
    }
}
