# Enterprise CRM SaaS Platform

An enterprise-grade, full-stack Customer Relationship Management (CRM) application with a **React + Vite + TypeScript** frontend and a **Laravel 12 (PHP / MySQL)** backend architecture.

---

## 📁 Project Structure

```text
crm-enterprises/
├── frontend/                   # React + Vite + TypeScript Frontend Application
│   ├── assets/                 # App assets & studio configurations
│   ├── src/                    # UI Components, state stores, and views
│   │   ├── components/         # Modular UI widgets, layouts, modals, and tables
│   │   ├── lib/                # Export utilities and helper functions
│   │   ├── store/              # Zustand state management (crmStore.ts)
│   │   ├── App.tsx             # Root application component
│   │   ├── index.css           # Tailwind styling entry
│   │   ├── main.tsx            # React application entry point
│   │   └── types.ts            # Enterprise TypeScript domain interfaces
│   ├── index.html              # Frontend HTML entry point
│   ├── server.ts               # Express & Vite development server
│   ├── server_db.ts            # In-memory / mock database engine
│   ├── crm_db.json             # Seed database records
│   ├── package.json            # Node.js dependencies and run scripts
│   ├── tsconfig.json           # TypeScript configuration
│   └── vite.config.ts          # Vite build & plugin configuration
│
├── backend/                    # Laravel 12 Enterprise Backend API
│   ├── app/                    # Controllers, Models, and Service Providers
│   ├── bootstrap/              # Framework bootstrapping & providers
│   ├── config/                 # Application configuration files
│   ├── database/               # Database migrations, seeders, and factories
│   ├── public/                 # Web server entry point (index.php)
│   ├── resources/              # Backend views, assets, and styling
│   ├── routes/                 # API & Web route definitions
│   ├── storage/                # Logs, sessions, and file uploads
│   ├── artisan                 # Laravel CLI management tool
│   └── composer.json           # PHP dependencies & autoloader
│
├── .gitignore                  # Monorepo root ignore definitions
└── README.md                   # Project overview and setup documentation
```

---

## 🚀 Getting Started

### 1. Frontend Setup (React + TypeScript + Vite)

Navigate to the `frontend` folder and install dependencies:

```bash
cd frontend
npm install
```

To start the development server:

```bash
npm run dev
```

The frontend app will launch at `http://localhost:3000`.

To build for production:

```bash
npm run build
```

---

### 2. Backend Setup (Laravel 12 API)

Navigate to the `backend` folder:

```bash
cd backend
composer install
```

Configure your environment:

1. Copy `.env.example` to `.env` (if not already created):
   ```bash
   cp .env.example .env
   ```
2. Generate an application encryption key:
   ```bash
   php artisan key:generate
   ```
3. Configure your database connection in `.env` (e.g. MySQL credentials).
4. Run migrations:
   ```bash
   php artisan migrate
   ```
5. Start the backend development server:
   ```bash
   php artisan serve
   ```

The backend API will run at `http://127.0.0.1:8000`.
