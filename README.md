# The Heartland Abode

AI-Enabled Smart Hotel Management System developed for academic evaluation and practical portfolio demonstration.

## Project Summary

The Heartland Abode is a full-stack hotel management platform integrating:
- Smart reservations and room management
- Dining and food operations
- Admin and manager dashboards
- Dynamic pricing logic
- Engineering economics and analytics
- Secure authentication and session handling

This repository is prepared for:
- GitHub submission
- GitHub Pages presentation
- University PBL evaluation

## Student Information

- Name: Yogesh Kumar
- Registration No: 2427030798
- Mentor: Dr. Tapan Kumar
- Email: yogesh.2427030798@muj.manipal.edu
- Department: Computer Science & Engineering
- Institute: Manipal University Jaipur

## Technology Stack

- PHP
- MySQL / MariaDB
- HTML5
- CSS3
- JavaScript (Vanilla)
- Chart.js
- MDB UI Kit

## Repository Entry Points

- PBL Showcase (static): `index.html`
- Main HMS App (PHP): `index.php`
- Demo fallback (GitHub Pages safe): `demo/index.html`
- Admin login: `admin/login.php`

## Core Modules

### User Side
- Room browsing and details
- Booking workflow
- Reservation history
- Dining catalog

### Admin Side
- Reservation and room operations
- Guest and staff management
- Food & dining management
- Analytics dashboards

### Intelligence and Economics
- Dynamic pricing support
- Revenue and occupancy insights
- Cost tracking and profitability indicators
- Decision-focused KPI visualizations

## Local Setup (XAMPP)

1. Place project in htdocs:
   - `/Applications/XAMPP/xamppfiles/htdocs/heartland_abode copy 2/`
2. Start Apache and MySQL
3. Create database: `heartland_abode`
4. Import SQL files in order:
   - `database/heartland_abode.sql`
   - `database/fix_admin_login.sql`
5. Create `.env` from `.env.example` and update DB credentials if needed

## Local URLs

- PBL Showcase: `http://localhost/heartland_abode%20copy%202/index.html`
- HMS App: `http://localhost/heartland_abode%20copy%202/index.php`
- Admin Login: `http://localhost/heartland_abode%20copy%202/admin/login.php`

## Admin Credentials

- Email: `yogeshkumar@heartlandabode.com`
- Username: `Yogesh Admin`
- Password: `Admin@123`

## GitHub Pages Compatibility

GitHub Pages cannot execute PHP/MySQL backend directly.

To support evaluation on static hosting, this repository includes:
- `index.html` as PBL-ready presentation page
- `demo/index.html` as static demo walkthrough
- Relative asset paths for Pages compatibility
- Live Demo buttons pointing to backend route when available

## Security and Repo Hygiene

- Environment-driven config via `.env`
- `.env` excluded from git via `.gitignore`
- Runtime and OS junk files removed
- Media assets consolidated under `assets/`
- Default fallback behavior for missing media retained

## License

MIT License (`LICENSE`)
