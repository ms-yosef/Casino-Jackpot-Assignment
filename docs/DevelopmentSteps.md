# Process of developing - step-by-step

## Initial stage
- Thinking...
- Choose stack for project
- Created project structure
- Set up environment configuration:
    - Created bin/generate_env.php for automatic generation of environment-specific .env files
    - Implemented JSON format for grouped settings like CHEAT_CONFIG
    - Added environment-specific flags like CHEAT_ENABLED
- Created .gitignore file
- Created composer.json, installed dependencies

## Repository stage
- Previous "monolith" repository was converted to monorepo
- Created packages:
    - casino_client
    - casino_server

## Client-side
- Implemented minimalistic UI:
     - table with 3 blocks in 1 row
     - button 'SPIN' to do new roll (attempt) 
     - implemented a spinning animation for each action (trembling, spinning, etc.)
     - implemented a cash-out button that moves credits from the game session (to the user's account on the server in the future) and closes the session
     - minimalistic UI for the result of the roll
- Implemented autonomy mode:
    - implemented a random number generator for the result of the roll

## Server-side
- Implemented using of Swagger OpenApi for API documentation
- Implemented API endpoints:
    - GET /api/game/config
    - POST /api/game/roll
    - POST /api/game/cashout
    - POST /api/game/session
- Implemented DTO classes
- Implemented Interfaces (Factory, Repository, Service)

