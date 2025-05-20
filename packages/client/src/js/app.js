/**
 * Casino Jackpot Slot Machine
 * Client-side game logic
 */

$(document).ready(function() {
    // Cache jQuery selectors
    const $spinButton = $('#spin-button');
    const $cashoutButton = $('#cashout-button');
    const $balance = $('#balance');
    const $spinCost = $('#spin-cost');
    const $slotItems = $('.slot-item');
    const $resultMessage = $('#result-message');
    const $resultAlert = $('#result-alert');
    const $demoModeAlert = $('<div class="alert alert-warning mt-3">DEMO MODE: Server is not available. Cashout is disabled.</div>').hide();
    
    // Add demo mode alert after the slot machine
    $('#slot-machine').after($demoModeAlert);

    // Flag to track if we're in demo mode
    let isDemoMode = false;

    // Default configuration (will be overridden by server config)
    let gameConfig = {
        initialCredits: 10,
        spinCost: 1,
        symbols: ['C', 'L', 'O', 'W'],
        symbolValues: {
            'C': 10, // Cherry
            'L': 20, // Lemon
            'O': 30, // Orange
            'W': 40  // Watermelon
        },
        symbolNames: {
            'C': 'Cherry',
            'L': 'Lemon',
            'O': 'Orange',
            'W': 'Watermelon'
        },
        jackpotSymbol: "",
        jackpotMultiplier: 1
    };

    // Game state
    let balance = 0;
    let isSpinning = false;
    let sessionId = null;
    let serverCheckInterval = null; // Interval for checking server status
    let timeoutInterval = 5000; // 5 seconds timeout for server availability check

    // API base URL
    const apiBaseUrl = 'http://localhost:8081';

    // Update displayed values
    function updateDisplay() {
        $balance.text(balance);
        $spinCost.text(gameConfig.spinCost);
    }

    // Function to get a random symbol
    function getRandomSymbol() {
        return gameConfig.symbols[Math.floor(Math.random() * gameConfig.symbols.length)];
    }

    // Function to check for a win
    function checkWin(results) {
        // Check if all symbols are the same
        let result;
        if (results[0] === results[1] && results[1] === results[2]) {
            const symbol = results[0];
            const winAmount = gameConfig.symbolValues[symbol];

            // Winner
            result = {
                win: winAmount,
                message: `Congratulations! Full set of ${getSymbolName(symbol)}s!`,
                type: 'success',
                isJackpot: false
            };
        } else {
            // No win
            result = {
                win: 0,
                message: 'Sorry, you lost. Try again!',
                type: 'danger',
                isJackpot: false
            };
        }

        return result;
    }

    // Get full symbol name from symbol code
    function getSymbolName(symbol) {
        return gameConfig.symbolNames[symbol] || symbol;
    }

    // Function to animate spinning
    function animateSpin(callback) {
        $slotItems.addClass('spinning');
        $slotItems.text('X');

        // Animation interval
        let iterations = 0;
        const spinInterval = setInterval(function() {
            $slotItems.each(function() {
                // Randomly change symbols during spinning for visual effect
                const randomSymbol = getRandomSymbol();
                $(this).text(randomSymbol);
            });

            iterations++;
            if (iterations >= 10) {
                clearInterval(spinInterval);
                callback();
            }
        }, 100);
    }

    // Function to display results sequentially
    function displayResults(results, updateBalanceLocally = false, serverWinInfo = null) {
        // Display first result after 1 second
        setTimeout(function() {
            $('#slot-1').removeClass('spinning').text(results[0]);

            // Display second result after 2 seconds
            setTimeout(function() {
                $('#slot-2').removeClass('spinning').text(results[1]);

                // Display third result after 3 seconds
                setTimeout(function() {
                    $('#slot-3').removeClass('spinning').text(results[2]);

                    // Use server win info if provided, otherwise check locally
                    let result;
                    if (serverWinInfo !== null) {
                        // Use server's win information
                        result = serverWinInfo;
                        
                        if (result.newBalance !== undefined) {
                            balance = result.newBalance;
                            updateDisplay();
                        }
                    } else {
                        // Check for win locally (for demo mode)
                        result = checkWin(results);
                        
                        // Only update balance locally if specified
                        if (updateBalanceLocally && result.win > 0) {
                            balance += result.win;
                            updateDisplay();
                        }
                    }

                    // Highlight winning symbols if there's a win
                    if (result.win > 0) {
                        $slotItems.addClass('win');
                        setTimeout(function() {
                            $slotItems.removeClass('win');
                        }, 3000);
                    }

                    showResult(result);

                    isSpinning = false;
                    $spinButton.prop('disabled', false);

                    // Check if game over
                    if (balance < gameConfig.spinCost) {
                        setTimeout(function() {
                            showResult({
                                win: 0,
                                message: 'Game over! You ran out of credits.',
                                type: 'warning',
                                isJackpot: false
                            });
                            $spinButton.prop('disabled', true);
                        }, 2000);
                    }
                }, 1000); // 3rd result (3 seconds total)
            }, 1000); // 2nd result (2 seconds total)
        }, 1000); // 1st result (1 second total)
    }

    // Function to reset slot display to default
    function resetSlots() {
        $slotItems.text('X').removeClass('spinning');
        $resultMessage.empty();
        $resultAlert.hide();
    }

    // SPIN button click handler
    $spinButton.on('click', function() {
        // Check if insufficient credits
        if (balance < gameConfig.spinCost) {
            showResult({
                win: 0,
                message: 'Insufficient credits to spin!',
                type: 'danger',
                isJackpot: false
            });
            return;
        }
        
        // Check if already spinning
        if (isSpinning) {
            return;
        }

        // Deduct cost and update display
        balance -= gameConfig.spinCost;
        updateDisplay();

        // Disable button during spin
        isSpinning = true;
        $(this).prop('disabled', true);

        // Start spinning animation
        animateSpin(function() {
            // If in demo mode, use local logic for spinning
            if (isDemoMode) {
                // Generate random result locally
                const result = generateLocalSpinResult();
                
                // Display result
                displayResults(result.reels.map(reel => reel[1]), true);
                
                // Re-enable spin button
                isSpinning = false;
                $spinButton.prop('disabled', false);
                
                // Check if game over
                if (balance < gameConfig.spinCost) {
                    setTimeout(function() {
                        showResult({
                            win: 0,
                            message: 'Game over! Reload to play again.',
                            type: 'warning',
                            isJackpot: false
                        });
                        $spinButton.prop('disabled', true);
                    }, 2000);
                }
                
                return;
            }
            
            // If not in demo mode, use server for spinning
            $.ajax({
                url: apiBaseUrl + '/api/game/spin',
                method: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify({
                    sessionId: sessionId,
                    betAmount: gameConfig.spinCost
                }),
                timeout: timeoutInterval,
                success: function(response) {
                    console.log('Server response:', response);

                    if (response.success && response.data) {
                        // Extract symbols from the first row of the matrix
                        // Server sends data in format [["L", "L", "L"]] (rows-based)
                        const results = response.data.reels[0] || [];

                        let newBalance = response.currentBalance;

                        // Create win info object based on server data
                        const serverWinInfo = {
                            win: response.data.winAmount || 0,
                            message: response.data.winAmount > 0 
                                ? `Congratulations! You won ${response.data.winAmount} credits!` 
                                : 'Sorry, you lost. Try again!',
                            type: response.data.winAmount > 0 ? 'success' : 'danger',
                            isJackpot: false, // Server would indicate if it's a jackpot
                            newBalance: newBalance
                        };

                        // Display results with server's win information
                        displayResults(results, false, serverWinInfo);
                    } else {
                        console.error('Invalid spin response format:', response);
                        // Simulate results for demo
                        const simulatedResults = [
                            getRandomSymbol(),
                            getRandomSymbol(),
                            getRandomSymbol()
                        ];
                        displayResults(simulatedResults, true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error spinning:', error);
                    
                    if (!isDemoMode) {
                        enableDemoMode();
                    }
                    
                    // Simulate results for demo
                    const simulatedResults = [
                        getRandomSymbol(),
                        getRandomSymbol(),
                        getRandomSymbol()
                    ];
                    displayResults(simulatedResults, true);
                }
            });
        });
    });

    // CASH OUT button click handler
    $cashoutButton.on('click', function() {
        if (isDemoMode) {
            showResult({
                win: 0,
                message: 'Cash out is disabled in demo mode!',
                type: 'warning',
                isJackpot: false
            });
            return;
        }
        
        if (isSpinning) {
            showResult({
                win: 0,
                message: 'Cannot cash out while spinning!',
                type: 'warning',
                isJackpot: false
            });
            return;
        }

        // In a real app, this would be an API call to the server
        $.ajax({
            url: apiBaseUrl + '/api/game/cashout',
            method: 'POST',
            dataType: 'json',
            contentType: 'application/json',
            data: JSON.stringify({ sessionId: sessionId }),
            timeout: timeoutInterval,
            success: function(response) {
                console.log('Cash out successful:', response);

                if (response.success && response.data) {
                    showResult({
                        win: response.data.amount,
                        message: `Successfully cashed out ${response.data.amount} credits!`,
                        type: 'success',
                        isJackpot: false
                    });
                } else {
                    showResult({
                        win: balance,
                        message: `Successfully cashed out ${balance} credits!`,
                        type: 'success',
                        isJackpot: false
                    });
                }

                // Reset game
                balance = 0;
                updateDisplay();
                resetSlots();
                $spinButton.prop('disabled', true);
                $(this).prop('disabled', true);
            },
            error: function(xhr, status, error) {
                console.error('Error cashing out:', error);
                // For demo, simulate successful cash out
                showResult({
                    win: balance,
                    message: `DEMO: Successfully cashed out ${balance} credits!`,
                    type: 'success',
                    isJackpot: false
                });

                // Reset game
                balance = 0;
                updateDisplay();
                resetSlots();
                $spinButton.prop('disabled', true);
                $(this).prop('disabled', true);
            }
        });
    });

    // Function to display result message
    function showResult(result) {
        const alertClass = `alert-${result.type}`;
        
        const fragment = document.createDocumentFragment();
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${alertClass}`;
        alertDiv.textContent = result.message;
        
        if (result.win > 0) {
            const strong = document.createElement('strong');
            strong.textContent = ` Win: ${result.win} credits`;
            alertDiv.appendChild(strong);
        }
        
        fragment.appendChild(alertDiv);
        
        $resultMessage.empty().append(fragment).removeClass('d-none');

        // Hide message after 3 seconds
        setTimeout(function() {
            $resultMessage.addClass('d-none');
        }, 3000);
    }

    // Update rewards display based on config
    function updateRewardsDisplay() {
        const fragment = document.createDocumentFragment();
        
        if (!gameConfig.symbols || !Array.isArray(gameConfig.symbols)) {
            console.error('Invalid symbols configuration:', gameConfig.symbols);
            gameConfig.symbols = ['C', 'L', 'O', 'W'];
        }
        
        for (const symbol of gameConfig.symbols) {
            const name = gameConfig.symbolNames[symbol] || symbol;
            const value = gameConfig.symbolValues[symbol] || 0;
            const listItem = document.createElement('li');
            listItem.className = 'list-group-item';
            listItem.textContent = `${symbol} - ${name} (${value} credits)`;
            fragment.appendChild(listItem);
        }
        
        $('.list-group').empty().append(fragment);
    }

    // Function to check server availability
    function checkServerAvailability() {
        $.ajax({
            url: apiBaseUrl + '/api/game/ping',
            method: 'GET',
            timeout: 3000, // 3 seconds timeout
            success: function() {
                if (isDemoMode) {
                    console.log('Server is now available. Exiting demo mode.');
                    disableDemoMode();
                    loadGameConfig(); // Reload configuration from server
                }
            },
            error: function() {
                if (!isDemoMode) {
                    console.log('Server is not available. Switching to demo mode.');
                    enableDemoMode();
                }
            }
        });
    }

    // Enable demo mode when server is not available
    function enableDemoMode() {
        isDemoMode = true;
        $demoModeAlert.show();
        $cashoutButton.prop('disabled', true);
        
        // Use default configuration
        if (!balance) {
            initGame();
        }
        
        showResult({
            win: 0,
            message: 'Server is not available. Running in DEMO MODE.',
            type: 'warning',
            isJackpot: false
        });
        
        // Start checking for server availability every 10 seconds
        if (!serverCheckInterval) {
            serverCheckInterval = setInterval(checkServerAvailability, 10000);
        }
    }
    
    // Disable demo mode when server becomes available
    function disableDemoMode() {
        isDemoMode = false;
        $demoModeAlert.hide();
        $cashoutButton.prop('disabled', false);
        
        // Stop checking for server availability
        if (serverCheckInterval) {
            clearInterval(serverCheckInterval);
            serverCheckInterval = null;
        }
    }

    // Load game configuration from server
    function loadGameConfig() {
        $.ajax({
            url: apiBaseUrl + '/api/game/config',
            method: 'GET',
            timeout: timeoutInterval,
            success: function(response) {
                if (response.success) {
                    console.log('Server config loaded:', response.data);
                    
                    const defaultConfig = { ...gameConfig };
                    
                    gameConfig = {
                        ...defaultConfig,
                        initialCredits: response.data.initialCredits || defaultConfig.initialCredits,
                        spinCost: response.data.spinCost || defaultConfig.spinCost,
                        symbols: Array.isArray(response.data.symbols) ? response.data.symbols : defaultConfig.symbols,
                        symbolNames: response.data.symbolNames || defaultConfig.symbolNames,
                        symbolValues: response.data.symbolValues || defaultConfig.symbolValues,
                        jackpotSymbol: response.data.jackpotSymbol || defaultConfig.jackpotSymbol,
                        jackpotMultiplier: response.data.jackpotMultiplier || defaultConfig.jackpotMultiplier
                    };
                    
                    console.log('Final config:', gameConfig);
                    updateRewardsDisplay();
                    disableDemoMode();
                    initGame();
                } else {
                    console.error('Error loading game config:', response.message);
                    enableDemoMode();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading game config:', error);
                enableDemoMode();
            }
        });
    }
    
    // Initialize game
    function initGame() {
        console.log('Initializing game with config:', gameConfig);

        // Reset slots to initial state
        resetSlots();
        
        // Enable spin button
        $spinButton.prop('disabled', false);
        
        // Only enable cashout button if not in demo mode
        if (!isDemoMode) {
            $cashoutButton.prop('disabled', false);
        } else {
            $cashoutButton.prop('disabled', true);
        }

        // Create session
        $.ajax({
            url: apiBaseUrl + '/api/game/session',
            method: 'POST',
            dataType: 'json',
            contentType: 'application/json',
            data: JSON.stringify({ initialBalance: gameConfig.initialCredits }),
            timeout: timeoutInterval,
            success: function(response) {
                console.log('Session created:', response);

                if (response.success && response.data) {
                    sessionId = response.data.sessionId;
                    balance = response.data.balance || gameConfig.initialCredits;
                    updateDisplay();
                } else {
                    console.error('Invalid session response format:', response);
                    // Fallback to demo mode
                    sessionId = 'demo-' + Math.floor(Math.random() * 1000);
                    balance = gameConfig.initialCredits;
                    updateDisplay();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error creating session:', error);
                // For demo, use default values
                sessionId = 'demo-' + Math.floor(Math.random() * 1000);
                balance = gameConfig.initialCredits;
                updateDisplay();
            }
        });
    }

    // Generate a local spin result for demo mode
    function generateLocalSpinResult() {
        // Generate random reels
        const reels = [
            [getRandomSymbol(), getRandomSymbol(), getRandomSymbol()],
            [getRandomSymbol(), getRandomSymbol(), getRandomSymbol()],
            [getRandomSymbol(), getRandomSymbol(), getRandomSymbol()]
        ];
        
        // Calculate win amount based on middle row
        const middleRow = [reels[0][1], reels[1][1], reels[2][1]];
        let winAmount = 0;
        let isJackpot = false;
        let message = 'No win this time!';
        let type = 'info';
        
        // Check for three of a kind
        if (middleRow[0] === middleRow[1] && middleRow[1] === middleRow[2]) {
            const symbol = middleRow[0];
            winAmount = gameConfig.symbolValues[symbol] || 1;
            
            if (gameConfig.jackpotSymbol && symbol === gameConfig.jackpotSymbol) {
                winAmount *= gameConfig.jackpotMultiplier;
                isJackpot = true;
                message = 'JACKPOT! Congratulations!';
                type = 'success';
            } else {
                message = 'You won!';
                type = 'success';
            }
        }
        
        return {
            reels: reels,
            win: winAmount,
            message: message,
            type: type,
            isJackpot: isJackpot
        };
    }

    function initializeApp() {
        checkServerAvailability();
        
        if (!isDemoMode) {
            loadGameConfig();
        } else {
            initGame();
        }
    }
    
    initializeApp();
});
