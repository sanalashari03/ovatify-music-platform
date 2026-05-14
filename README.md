# 🎵 Ovatify - Your Ultimate Music Platform

<p align="center">
  <img src="public/screenshots/home.png" alt="Ovatify Home" width="800">
</p>

Ovatify is a modern music creation and management platform built with **Laravel**. It allows creators to manage their tracks, explore new content, and handle investments and marketplace activities with a premium, sleek dark-themed interface.

## ✨ Features

- **Dashboard**: Track your earnings, publishes, and drafts at a glance.
- **AI Integration**: Create new tracks using AI-powered tools.
- **Marketplace**: Explore, buy, and sell music content (Beats, Vocals, Loops, etc.).
- **User Authentication**: Secure sign-up, login, and email verification.
- **Premium UI**: Modern dark mode design with vibrant accents.

## 📸 Screenshots

### Home Dashboard
![Home](public/screenshots/home.png)

### My Tracks
![My Tracks](public/screenshots/dashboard.png)

### Marketplace
![Marketplace](public/screenshots/marketplace.png)

### Verification Flow
![Verification](public/screenshots/verification.png)

### Sign Up
![Sign Up](public/screenshots/signup.png)

## 🚀 Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/YOUR_USERNAME/ovatify-app.git
   ```

2. **Install dependencies**:
   ```bash
   composer install
   ```

3. **Environment Setup**:
   Copy `.env.example` to `.env` and configure your database settings.
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database Configuration**:
   Run the database creation script and migrations:
   ```bash
   php create_db.php
   php artisan migrate
   ```

5. **Run the application**:
   ```bash
   php artisan serve
   ```

## 🛠 Tech Stack

- **Backend**: Laravel 12.x
- **Frontend**: Blade Templates, Tailwind CSS
- **Database**: MySQL
- **Tools**: Vite, Composer

## 📄 License

The Ovatify platform is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
