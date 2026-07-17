# ICT Help Desk — Support Ticket System

A full-stack help desk and ticketing system built with PHP and MySQL, designed for internal ICT support teams to track, assign, and resolve support requests with SLA accountability.

Built independently, end-to-end — from requirements gathering through deployment — as a real-world solution to a support tracking gap in an internal ICT environment.

## Overview

Support requests often arrive scattered across emails, phone calls, and in-person handoffs with no shared record, no clear ownership, and no way to tell what's overdue. This system centralizes that workflow: every request becomes a tracked ticket, routed by department, timed against a response-time SLA, and escalated automatically if it's at risk of breaching that deadline.

## Features

- **Email-to-ticket automation** — inbound support emails are parsed and converted into tracked tickets automatically, with a fallback delivery path if the primary mail channel is unavailable
- **SLA monitoring & escalation** — every ticket carries a response-time clock based on priority, with automatic escalation as deadlines approach or are missed
- **Role-based access control** — staff, technicians, and administrators each see only what's relevant to their role
- **Full audit trail** — every status change, reassignment, and update is logged against the ticket
- **HTTPS enforced by default** — encrypted in transit across the deployed application
- **File attachments** — screenshots and supporting files attach directly to tickets
- **Notifications** — in-app alerts for assignment, updates, and SLA warnings
- **Reporting dashboard** — ticket volume, resolution time, and department breakdowns

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 |
| Database | MySQL |
| Web server | Apache 2.4 |
| Email | PHPMailer, SMTP/IMAP, with API-based fallback delivery |
| Frontend | HTML5, CSS3, vanilla JavaScript |
| Security | HTTPS/TLS, CSRF protection, prepared statements, rate-limited login attempts |
| Environment | Windows Server, deployed via XAMPP stack |

## Architecture Notes

- Environment-based configuration (`.env`) keeps all credentials and secrets out of source control
- CSRF tokens on all state-changing forms
- Login attempts are rate-limited and logged to deter brute-force attempts
- Scheduled task handles SLA escalation checks and inbound email polling independently of user requests


## Status

Actively maintained. Originally built and piloted in a live internal ICT support environment.

## Author

Built by [Elvis Mwaura](https://github.com/thukuthuku217-bot) — full-stack development, Windows Server administration, and network infrastructure.
