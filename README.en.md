# Tribal Wars Game Engine

This project is a modern implementation of a browser-based game engine similar to Tribal Wars, built with pure PHP, HTML, CSS, and JavaScript. The project was inspired by an older version of the engine made by Bartekst221 but has been completely rewritten using modern practices.

## Implemented Functionalities

The main functionalities implemented in the project are:

1.  **Registration and Login System**
    -   Secure password storage using modern algorithms.
    -   Input data validation.
    -   Session management.

2.  **Village Management System**
    -   Creation of new villages.
    -   Changing village names.
    -   Population management.
    -   Real-time resource production.

3.  **Building System**
    -   Construction and upgrading of buildings.
    -   Building dependency system.
    -   Level-dependent construction costs and times.
    -   Special bonuses from buildings.
    -   Dynamic building queue with time management.
    -   Cancellation of construction.

4.  **Resource System**
    -   Production of wood, clay, and iron.
    -   Resource storage.
    -   Automatic real-time resource updates.
    -   Upgrading of resource production buildings.

5.  **Real-Time System**
    -   Buildings are constructed in real-time.
    -   Units are recruited in real-time.
    -   Resources are produced in real-time.

6.  **Map System**
    -   World map visualization with a fluid interface.
    -   X/Y coordinates for villages.
    -   Attack interface opened in a modal window directly from the map.

7.  **Military System**
    -   Various unit types with unique stats.
    -   Fully implemented unit recruitment system in the barracks, stable, and workshop.
    -   Dynamic recruitment queue with cancellation options.
    -   Advanced combat system that includes:
        -   Proportional loss calculation based on the strength of both armies.
        -   Defensive bonus from the wall's level.
        -   Ability for rams to destroy walls.
        -   Ability for catapults to target and destroy buildings.
    -   Ability to send attacks and support to other villages.

8.  **Messaging and Reporting System**
    -   Sending messages between players (in progress).
    -   Detailed battle reports, including information on losses, loot, and destruction.
    -   Alliance and tribe system (planned).

## Inspirations from VeryOldTemplate

The old version of the engine (VeryOldTemplate) was used as inspiration for the following solutions:

1.  **Building System**
    -   The structure of the `building_requirements` table is similar to `needbuilds` from VeryOldTemplate.

2.  **Village System**
    -   The resource update system works on a similar principle.
    -   Automatic checking for the completion of building constructions.

3.  **Helper Functions System**
    -   Many helper functions in `lib/functions.php` (if they exist) were inspired by old functions.
    -   Time and date formatting system.
    -   Functions for calculating distances and other parameters.

## Improvements Over the Old Version

1.  **Security**
    -   Transition from the outdated `mysql_*` to `mysqli` with prepared statements.
    -   Better password hashing.
    -   Validation of all input data.
    -   Separation of API and frontend - using AJAX for communication with the backend.

2.  **Code Structure**
    -   Greater modularity and code reusability.
    -   Use of classes and objects (Managers, Models).
    -   Separation of business logic from presentation.

3.  **Functionality**
    -   More flexible building system.
    -   Expanded building dependency system.
    -   Detailed descriptions and bonuses for buildings (based on configuration).
    -   Dynamic updates of resources, construction queues, and recruitment on the frontend (AJAX, JavaScript).

4.  **Database**
    -   Better designed table structure.
    -   Relationships between tables using foreign keys.
    -   Indexes for faster searching.

### UI/UX Improvements
- **Improved Styles** - A modern, consistent look while maintaining the Tribal Wars aesthetic.
- **Smoother Interface** - Introduction of modal windows for key actions (e.g., sending attacks from the map), eliminating the need for page reloads.
- **Tooltips** - Adding hints for interface elements (in progress).
- **Progress Bars** - Animated progress bars for construction and recruitment.
- **Responsiveness** - Better adaptation to different devices (in progress).
- **Toast Notification System** - For better user feedback.

## Project Structure

```
├── ajax/
│   ├── buildings/      # AJAX endpoints related to buildings
│   └── units/          # AJAX endpoints related to units
├── config/             # Configuration files (e.g., config.php)
├── css/                # CSS styles (main.css)
├── docs/               # Project documentation
├── game/               # Main game files (game.php, map.php)
├── img/                # Images and graphics
├── js/                 # JavaScript scripts
├── lib/                # PHP classes
│   └── managers/       # Classes managing business logic
├── logs/               # Application logs
├── *.php               # Main application files (index.php, install.php)
└── readme.md           # This file
```

## Installation

1.  Clone or download the project files to the `htdocs` directory in XAMPP.
2.  Make sure you have a running MySQL server (part of XAMPP).
3.  Create a MySQL database named `tribal_wars_new`.
4.  Import the database structure by running the `sql_create_*.sql` scripts located in the `docs/sql` directory (e.g., using phpMyAdmin or a MySQL client). You can also use the `install.php` script.
5.  Configure the `config/config.php` file with your database connection details (username, password - by default, root and no password for XAMPP).
6.  Open the page in your browser: `http://localhost/`
7.  Follow the on-screen instructions (registration, village creation).

## Documentation

Detailed documentation of the code and database can be found in the `docs/` directory (if it exists). It may contain files such as `database.md`, `api.md`, etc.

## Further Development

The project can be further developed by implementing and expanding planned functionalities, such as:
1.  Alliance/tribe system.
2.  Player-to-player trading system.
3.  Rewards and achievements system.
4.  Further balancing of units and the combat system.
5.  Implementation of a spying system.
6.  Completing action panels for the remaining buildings (Smithy, Market, etc.).
7.  Further improvements to UI/UX and responsiveness.

## Authors

The project is based on the game plemiona.pl, rewritten and developed by PSteczka.

## License

This project is available under the MIT License.