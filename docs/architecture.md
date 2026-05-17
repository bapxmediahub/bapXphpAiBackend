# Architecture

The app is a modular PHP monorepo with a route registry as its frozen functional contract. Each route maps to a controller, view, and declared services. Services own domain logic and JSON collections own persistence.
