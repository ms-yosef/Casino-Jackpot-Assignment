# Casino Jackpot Assignment

## Objective

Congratulations! You've landed a summer gig in Las Vegas! Unfortunately, it's 2020, and the casinos are closed due to COVID-19. Your boss wants to move some of the business online and asks you to build a full-stack app — a simple slot machine game with a twist. Build it to ensure that the house always wins!

## Brief

When a player starts a game/session, they are allocated 10 credits. Pulling the machine lever (rolling the slots) costs 1 credit. The game screen has 1 row with 3 blocks. For players to win the roll, they have to get the same symbol in each block. There are 4 possible symbols:

- Cherry (10 credits reward)
- Lemon (20 credits reward)
- Orange (30 credits reward)
- Watermelon (40 credits reward)

The game (session) state has to be kept on the server. If the player keeps winning, they can play forever, but the house has something to say about that... There is a CASH OUT button on the screen, but there's a twist there as well.

## Tasks

### General Requirements

- Implement the assignment using any language or framework you feel comfortable with.
- When a user opens the app, a session is created on the server, and they have 10 starting credits.

### Server-side

- When a user has less than 40 credits in the game session, their rolls are truly random.
- If a user has between 40 and 60 credits, the server begins to slightly cheat:
    - For each winning roll, before communicating back to the client, the server performs a 30% chance roll which decides if the server will re-roll that round.
    - If the roll is true, then the server re-rolls and communicates the new result back.
- If the user has above 60 credits, the server acts the same, but the chance of re-rolling the round increases to 60%.
    - If the roll is true, then the server re-rolls and communicates the new result back.
- There is a cash-out endpoint that moves credits from the game session to the user's account and closes the session.

### Client-side

- Include a super simple, minimalistic table with 3 blocks in 1 row.
- Include a button next to the table that starts the game.
- The components for each sign can be a starting letter (C for cherry, L for lemon, O for orange, W for watermelon).
- After submitting a roll request to the server, all blocks should enter a spinning state (can be 'X' character spinning).
- After receiving a response from the server:
    - The first sign should spin for 1 second more and then display the result.
    - The second sign should display the result at 2 seconds.
    - The third sign should display the result at 3 seconds.
- If the user wins the round, their session credit is increased by the amount from the server response, otherwise, it is deducted by 1.

## Evaluation Criteria

1. **Completeness**: Did you complete the features as briefed?
2. **Correctness**: Does the solution perform in sensible, thought-out ways?
3. **Maintainability**: Is the code written in a clean, maintainable way?
4. **Testing**: Was the system adequately tested?

## Code Submission

Please organize, design, test, and document your code as if it were going into production. Then push your changes to the master branch. After you have pushed your code, you may submit the assignment on the assignment page.