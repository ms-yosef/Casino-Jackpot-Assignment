/**
 * Casino Jackpot Slot Machine
 * Client-side game logic
 */

$(document).ready(function() {
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
        }
    };

    // Game state
    let balance = 0;
    let isSpinning = false;
    let sessionId = null;

    // Update displayed values
    function updateDisplay() {
        $('#balance').text(balance);
        $('#spin-cost').text(gameConfig.spinCost);
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
                message: `Congratulations! Three ${getSymbolName(symbol)}s!`,
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
        const $slotItems = $('.slot-item');
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
    function displayResults(results) {
        // Display first result after 1 second
        setTimeout(function() {
            $('#slot-1').removeClass('spinning').text(results[0]);

            // Display second result after 2 seconds
            setTimeout(function() {
                $('#slot-2').removeClass('spinning').text(results[1]);

                // Display third result after 3 seconds
                setTimeout(function() {
                    $('#slot-3').removeClass('spinning').text(results[2]);

                    // Check for win
                    const result = checkWin(results);

                    // Process result
                    if (result.win > 0) {
                        balance += result.win;

                        // Highlight winning symbols
                        $('.slot-item').addClass('win');
                        setTimeout(function() {
                            $('.slot-item').removeClass('win');
                        }, 3000);
                    }

                    // Update display and show result
                    updateDisplay();
                    showResult(result);

                    // Enable spin button
                    isSpinning = false;
                    $('#spin-button').prop('disabled', false);

                    // Check if game over
                    if (balance < gameConfig.spinCost) {
                        setTimeout(function() {
                            showResult({
                                win: 0,
                                message: 'Game over! You ran out of credits.',
                                type: 'warning',
                                isJackpot: false
                            });
                            $('#spin-button').prop('disabled', true);
                        }, 2000);
                    }
                }, 1000); // 3rd result (3 seconds total)
            }, 1000); // 2nd result (2 seconds total)
        }, 1000); // 1st result (1 second total)
    }

    // Function to reset slot display to default
    function resetSlots() {
        $('.slot-item').text('X').removeClass('spinning');
        $('#result-message').empty();
        $('#result-alert').hide();
    }

    // SPIN button click handler
    $('#spin-button').on('click', function() {
        // Check if spinning or insufficient credits
        if (isSpinning || balance < gameConfig.spinCost) {
            if (balance < gameConfig.spinCost) {
                showResult({
                    win: 0,
                    message: 'Insufficient credits to spin!',
                    type: 'danger',
                    isJackpot: false
                });
            }
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
            // In a real app, this would be an API call to the server
            $.ajax({
                url: 'http://localhost:8081/api/game/spin',
                method: 'POST',
                dataType: 'json',
                data: { sessionId: sessionId },
                success: function(data) {
                    console.log('Server response:', data);
                    // Use server results
                    displayResults(data.results);
                },
                error: function(xhr, status, error) {
                    console.error('Error spinning:', error);
                    // Simulate results for demo
                    const simulatedResults = [
                        getRandomSymbol(),
                        getRandomSymbol(),
                        getRandomSymbol()
                    ];
                    displayResults(simulatedResults);
                }
            });
        });
    });

    // CASH OUT button click handler
    $('#cashout-button').on('click', function() {
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
            url: 'http://localhost:8081/api/game/cashout',
            method: 'POST',
            dataType: 'json',
            data: { sessionId: sessionId },
            success: function(data) {
                console.log('Cash out successful:', data);
                showResult({
                    win: balance,
                    message: `Successfully cashed out ${balance} credits!`,
                    type: 'success',
                    isJackpot: false
                });

                // Reset game
                balance = 0;
                updateDisplay();
                resetSlots();
                $('#spin-button').prop('disabled', true);
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
                $('#spin-button').prop('disabled', true);
                $(this).prop('disabled', true);
            }
        });
    });

    // Function to display result message
    function showResult(result) {
        const alertClass = `alert-${result.type}`;
        let html = `<div class="alert ${alertClass}">${result.message}`;

        if (result.win > 0) {
            html += `<strong> Win: ${result.win} credits</strong>`;
        }

        html += '</div>';

        $('#result-message').html(html).removeClass('d-none');

        // Hide message after 3 seconds
        setTimeout(function() {
            $('#result-message').addClass('d-none');
        }, 3000);
    }

    // Load game configuration from server
    function loadGameConfig() {
        $.ajax({
            url: 'http://localhost:8081/api/game/config',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                console.log('Game config loaded:', data);
                // Merge server config with default config
                gameConfig = $.extend(gameConfig, data);

                // Update rewards display
                updateRewardsDisplay();

                // Initialize game after config is loaded
                initGame();
            },
            error: function(xhr, status, error) {
                console.error('Error loading game config:', error);
                // Use default config
                console.log('Using default (autonomy) game config');

                // Update rewards display
                updateRewardsDisplay();

                // Initialize game with default config
                initGame();
            }
        });
    }

    // Update rewards display based on config
    function updateRewardsDisplay() {
        let rewardsHtml = '';

        for (const symbol of gameConfig.symbols) {
            const name = gameConfig.symbolNames[symbol];
            const value = gameConfig.symbolValues[symbol];
            rewardsHtml += `<li class="list-group-item">${symbol} - ${name} (${value} credits)</li>`;
        }

        $('.list-group').html(rewardsHtml);
    }

    // Initialize game
    function initGame() {
        console.log('Initializing game with config:', gameConfig);
        
        // Reset slots to initial state
        resetSlots();
        
        // Enable spin button
        $('#spin-button').prop('disabled', false);
        $('#cashout-button').prop('disabled', false);
        
        // Create session
        $.ajax({
            url: 'http://localhost:8081/api/game/session',
            method: 'POST',
            dataType: 'json',
            success: function(data) {
                console.log('Session created:', data);
                sessionId = data.sessionId;
                balance = data.credits || gameConfig.initialCredits;
                updateDisplay();
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

    // Start by loading game configuration
    loadGameConfig();
});
