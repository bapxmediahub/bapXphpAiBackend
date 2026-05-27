# Architecture

The app is a modular PHP backend with React.js frontend components. The route registry serves as the functional contract mapping routes to controllers, views, and declared services.

## Frontend
- React.js for dynamic UI components and interactive elements
- Vanilla JavaScript for DOM manipulation and vanilla interactions
- CSS (band.css) for responsive styling and theming

## Backend
- PHP for server-side logic, routing, and API handling
- JSON file-based storage for persistence (no database required)
- Service-oriented architecture with domain logic separation

## Data Flow
1. Routes defined in `app/Services/ProjectMapService.php`
2. Controllers handle requests and render views
3. Services manage business logic
4. JSON collections in `storage/data/` handle data persistence
