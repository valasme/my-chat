# myChat

<p align="center">
	<img src="public/og.png" alt="myChat banner" width="100%" />
</p>

<p align="center">
	Secure, private messaging built with Laravel & Livewire.
</p>

---

> ⚠️ **Heavy Development in Progress**
>
> This repository is under active development and is not currently
> suitable for any kind of production or real-world usage. The database
> schema, APIs, and features change frequently; your data may be lost at
> any time. Only run this project if you understand it is _very_ early
> and you are prepared to rebuild from scratch when the next update
> lands.

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Requirements](#requirements)
- [Quick Start](#quick-start)
    - [Windows](#windows)
    - [Linux](#linux)
    - [macos](#macos)
- [Running the App](#running-the-app)
- [Demo Data](#demo-data)
- [Authentication and Access](#authentication-and-access)
- [Status Values](#status-values)
- [Testing](#testing)

## Overview

myChat is a simple one-on-one chat application that focuses on
secure, end-to-end encrypted messaging between individuals. It is
built as a learning project and currently lacks polish or stability.


## Features

- Basic one-on-one chat conversations.
- User-to-user contacts system (add/search/remove contacts by email, see details below)
- Placeholder for end-to-end encryption (not yet implemented).
- Authentication powered by Laravel Fortify.
- Responsive UI using Livewire, Flux UI, and Tailwind CSS.

## Contacts System

The contacts system lets users add other users as contacts, similar to a friends list. You can search for users by email, add them to your contacts, view their profile, and remove them if needed. Contacts are displayed in a searchable, sortable, and paginated list. Only authenticated users can manage contacts, and you can only view or remove your own contacts.

**Key features:**
- Add contacts by searching for a user's email address
- Prevents adding yourself or duplicate contacts
- Contacts list supports search (by name/email), sorting (name/email, asc/desc), and pagination (25 per page)
- View contact details (profile, email, when added)
- Remove contacts with confirmation
- Cascade deletes: contacts are removed if either user is deleted
- Authorization: only the contact owner can view or remove a contact

**Usage:**
- Go to Contacts in the app to view, add, or remove contacts.
- All actions are protected by authorization policies.


## Tech Stack

- Laravel 12
- Livewire 4 + Flux UI
- Tailwind CSS 4
- Laravel Fortify

## Requirements

- PHP 8.2+ (PHP 8.5 recommended)
- Composer (latest version recommended)
- Node.js and npm (Node.js 25 recommended)
- A Laravel-supported database (SQLite, MySQL, etc. SQLite is
  convenient for development)

## Quick Start

### Windows

```cmd
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

### Linux

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

### macOS

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

## Running the App

If you're on macOS or Windows and using Laravel Herd, the site will be
served automatically at the `.test` domain.

For manual development you can use:

```bash
composer run dev
```

This command starts the Laravel server, queue listener, and Vite dev
server together.

## Demo Data

```bash
php artisan db:seed
```

Seeds create a test user (`test@example.com`) and any other sample
records needed for development.

## Authentication and Access

- You must be logged in to access the dashboard or chat screens.
- Fortify handles login, registration, password resets, and profile
  updates. Two‑factor authentication is scaffolded but may not be live.

## Testing

Run the test suite with:

```bash
php artisan test --compact
```

Make sure to run the specific tests you modify during development.

---

> **Note:** This README was adapted from BasicEMS; many sections
> are intentionally minimal while the project matures. Expect large
> rewrites and data wipes very frequently.
