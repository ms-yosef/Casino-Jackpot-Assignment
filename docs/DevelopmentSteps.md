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
- Improved error handling:
    - Added demo mode detection when server is unavailable
    - Implemented automatic switching to demo mode when server connection fails
    - Added visual indication of demo mode with disabled cashout functionality

## Server-side
- Implemented using of Swagger OpenApi for API documentation
- Implemented API endpoints:
    - GET /api/game/config
    - POST /api/game/roll
    - POST /api/game/cashout
    - POST /api/game/session
    - GET /api/game/ping
- Implemented DTO classes
- Implemented Interfaces (Factory, Repository, Service)
- Created basic functionality for App:
    - DefaultGameFactory
    - InMemoryGameRepository
    - DefaultGameService
- Implemented Cheating logic:
    - If a user has between 40 and 60 credits, the server begins to slightly cheat:
        - For each winning roll, before communicating back to the client, the server performs a 30% chance roll which decides if the server will re-roll that round.
        - If the roll is true, then the server re-rolls and communicates the new result back.
    - If the user has above 60 credits, the server acts the same, but the chance of re-rolling the round increases to 60%.
        - If the roll is true, then the server re-rolls and communicates the new result back.
- Created MySQLGameRepository, app switched to store sessions in DB
- Added endpoint 'Ping'
- Improved session management:
    - Fixed session closure logic when player balance reaches zero
    - Enhanced logging for better debugging and monitoring
    - Added proper error handling for edge cases

## Testing
- Created Unit-tests for:
    - InMemoryGameRepository
    - DefaultGameFactory
    - DefaultGameService
- Created Functional tests
- Created Integration tests

## DevOps & Deployment
- Implemented CI/CD pipeline using GitHub Actions:
    - Automated testing on each push to dev branch
    - Automated PR creation for merging from dev to master
- Added environment-specific configuration for development and production
