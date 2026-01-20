# 🎫 Helpio

A modern, feature-rich helpdesk ticketing system built with Symfony, designed to streamline support operations with comprehensive ticket management, knowledge base, and reporting capabilities.

## ✨ Prerequisites

- 🐳 **Docker & Docker Compose** (for database and phpMyAdmin)
- 🐘 **PHP 8.4+**
- 📦 **Composer**
- 🟢 **Node.js** (optional, for asset management)

## 🚀 Quick Start

### 1. Clone the Repository

```bash
git clone https://github.com/JAWAD4HAM/Ticket_Tracking
cd <Your_Directory_Name>
```

### 2. Start Docker Containers

```bash
docker compose up -d
```
This starts MySQL 8.0 and phpMyAdmin (http://localhost:8081).

### 3. Install Dependencies

```bash
composer install
```

### 4. Run Database Migrations

```bash
php bin/console doctrine:migrations:migrate
```

### 5. Start the Development Server

```bash
symfony server:start
# OR
php -S localhost:8000 -t public
```

The application will be available at **http://localhost:8000**

## ⚙️ Configuration

Copy `.env` to `.env.local` for local configuration:
```bash
cp .env .env.local
```

### 🗄️ Database
configured in `compose.yaml`:
- **Host**: localhost
- **Port**: 3306
- **Database**: helpdesk
- **User**: helpdesk_user
- **Password**: helpdesk_pass

## 🔧 Troubleshooting

- 🔌 **Database connection fails**: Check `docker compose ps` and wait for MySQL to initialize.
- 🎨 **Assets not loading**: Run `php bin/console asset-map:compile`.
- 🔒 **Permission issues**: `chmod -R 777 var/`

## ℹ️ Project Info

- ⚡ **Framework**: Symfony 8.0
- 🗄️ **Database**: MySQL 8.0
- 🎨 **Frontend**: HTML/CSS, Twig, Turbo, Stimulus
- 🏗️ **ORM**: Doctrine
- 🌐 **API Format**: REST with Symfony routing