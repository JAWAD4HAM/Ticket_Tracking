# HelpioAdd
A modern, feature-rich helpdesk ticketing system built with Symfony, designed to streamline support operations with comprehensive ticket management, knowledge base, and reporting capabilities.

## Features

- **Ticket Management**: Create, assign, and track support tickets with priorities and categories
- **Multi-Role Access**: Support for Admin, Manager, Technician, and User roles
- **Knowledge Base**: Internal knowledge base for self-service support
- **SLA Management**: Track and monitor Service Level Agreements
- **Reporting**: Monthly reports with volume metrics, efficiency analysis, and team performance
- **Dashboard**: Role-specific dashboards for different user types
- **Real-time Updates**: Built with Turbo for seamless page transitions
- **Responsive Design**: Modern UI that works on all devices

## Prerequisites

- **Docker & Docker Compose** (for database and phpMyAdmin)
- **PHP 8.2+**
- **Composer**
- **Node.js** (optional, for asset management)

## Quick Start

### 1. Clone the Repository

```bash
git clone <repository-url>
cd HelpDesk
```

### 2. Start Docker Containers

```bash
docker compose up -d
```

This starts:
- **MySQL 8.0** database
- **phpMyAdmin** (accessible at http://localhost:8081)

### 3. Install Dependencies

```bash
composer install
```

### 4. Run Database Migrations

```bash
php bin/console doctrine:migrations:migrate
```

### 5. (Optional) Seed Initial Data

```bash
php bin/console app:seed-data
```

### 6. Start the Development Server

**Using Symfony CLI:**
```bash
symfony server:start
```

**Or using PHP built-in server:**
```bash
php -S localhost:8000 -t public
```

The application will be available at **http://localhost:8000**

## Project Structure

```
HelpDesk/
├── bin/
│   └── console              # CLI tool
├── config/
│   ├── bundles.php          # Symfony bundles configuration
│   ├── services.yaml        # Service definitions
│   ├── routes.yaml          # Route definitions
│   └── packages/            # Bundle-specific configs
├── migrations/              # Database migrations
├── public/
│   ├── css/                 # Stylesheets
│   ├── images/              # Images and logos
│   └── index.php            # Entry point
├── src/
│   ├── Controller/          # Application controllers
│   ├── Entity/              # Doctrine entities
│   ├── Form/                # Symfony forms
│   └── Repository/          # Database repositories
├── templates/               # Twig templates
├── assets/                  # JavaScript and CSS assets
├── translations/            # i18n translation files
├── var/                     # Cache and logs
└── compose.yaml             # Docker Compose configuration
```

## Configuration

### Environment Variables

Copy `.env` to `.env.local` for local configuration:

```bash
cp .env .env.local
```

Key variables:
- `APP_ENV`: Set to `dev` or `prod`
- `APP_DEBUG`: Set to `true` or `false`
- `DATABASE_URL`: Auto-configured via Docker Compose

### Database

The database is automatically configured in [`compose.yaml`](compose.yaml):
- **Host**: localhost
- **Port**: 3306
- **Database**: helpdesk
- **User**: helpdesk_user
- **Password**: helpdesk_pass

Access phpMyAdmin at **http://localhost:8081**

## User Roles

The system supports multiple user roles:

1. **Admin**: Full system access, user management, configuration
2. **Manager**: Dashboard access, reporting, team oversight
3. **Technician**: Ticket assignment, knowledge base management
4. **User**: Create tickets, view their own tickets, access knowledge base

## Key Routes

| Route | Purpose |
|-------|---------|
| `/login` | User authentication |
| `/dashboard` | Role-specific dashboard |
| `/ticket` | View all tickets |
| `/ticket/create` | Create new ticket |
| `/ticket/{id}` | View ticket details |
| `/kb` | Knowledge base |
| `/report/monthly` | Monthly report (Manager only) |
| `/admin/users` | User management (Admin only) |
| `/settings` | User settings |

## Database Schema

Key entities:
- **User**: System users with roles
- **Ticket**: Support tickets with status and priority
- **Category**: Ticket categories
- **Priority**: Priority levels (1-5)
- **Status**: Ticket statuses
- **TicketComment**: Comments on tickets
- **Attachment**: File attachments
- **KbArticle**: Knowledge base articles

## Available Commands

```bash
# Database migrations
php bin/console doctrine:migrations:migrate       # Run migrations
php bin/console doctrine:migrations:diff          # Generate migration

# Cache management
php bin/console cache:clear                       # Clear application cache

# Assets
php bin/console asset-map:compile                 # Compile assets

# Database
php bin/console doctrine:database:create          # Create database
php bin/console doctrine:database:drop            # Drop database
```

## Development

### Code Standards

The project follows PSR-12 coding standards. Use:

```bash
php -l src/                                        # Lint PHP files
```

### Testing

Run tests with:

```bash
php bin/phpunit
```

## Deployment

For production deployment:

1. Set `APP_ENV=prod` in `.env.local`
2. Set `APP_DEBUG=false`
3. Run: `composer install --no-dev --optimize-autoloader`
4. Run migrations: `php bin/console doctrine:migrations:migrate`
5. Clear cache: `php bin/console cache:clear`
6. Compile assets: `php bin/console asset-map:compile`

A `Procfile` is included for **Heroku** deployment.

## Troubleshooting

### Database connection fails
- Ensure Docker containers are running: `docker compose ps`
- Check database credentials in `.env.local`
- Verify MySQL is ready (wait ~10 seconds after starting containers)

### Migrations fail
- Ensure database exists: `php bin/console doctrine:database:create`
- Check for pending migrations: `php bin/console doctrine:migrations:status`

### Assets not loading
- Clear cache: `php bin/console cache:clear`
- Compile assets: `php bin/console asset-map:compile`

### Permission issues
- Ensure `var/` directory is writable: `chmod -R 777 var/`

## Support

For issues or questions:
1. Check the [Knowledge Base](http://localhost:8000/kb)
2. Contact your system administrator
3. Review logs in `var/log/`

## License

[License information here]

## Project Info

- **Framework**: Symfony 7.x
- **Database**: MySQL 8.0
- **Frontend**: HTML/CSS, Twig, Turbo, Stimulus
- **ORM**: Doctrine
- **API Format**: REST with Symfony routing