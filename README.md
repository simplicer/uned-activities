# UNED Activities Finder v1.1.3

![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)
![Status: Stable](https://img.shields.io/badge/Status-Stable-green.svg)
![Languages: 6](https://img.shields.io/badge/Languages-6-blue.svg)

Find and stay updated on UNED extension activities in your language of choice.

## What is UNED Activities Finder?

A free, multilingual platform that helps you discover, search, and stay informed about UNED (Universidad Nacional de Educación a Distancia) extension courses and activities.

### Why You Need It

UNED offers hundreds of extension activities each year, but they're scattered across multiple pages and hard to find. This platform:
- Centralizes all UNED activities in one searchable place
- Supports 6 languages (Spanish, English, Catalan, Valencian, Basque, Galician)
- Lets you filter by price, topic, schedule, and location
- Notifies you about new activities matching your interests

### Who Should Use It?
- Students looking for extension courses
- Professionals seeking continuous education
- Anyone interested in UNED learning opportunities

## Features

- **6 Languages:** Spanish, English, Catalan, Valencian, Basque, Galician
- **Advanced Search & Filters:** Topic, price, schedule, location, format
- **Smart Notifications:** Alerts for activities matching your interests
- **Price Transparency:** See all costs upfront
- **Save Searches:** Keep track of what interests you
- **Fast & Mobile-Friendly:** Optimized for all devices
- **100% Free:** Open source, no ads, no paywalls

## Getting Started

### Option 1: Use Online (Recommended)
1. Visit https://activities.uned.es
2. Browse or search for activities
3. (Optional) Create account for saved searches and notifications

### Option 2: Run Locally (Self-Hosted)

**Requirements:**
- Docker and Docker Compose (download from docker.com)
- 4GB RAM minimum
- ~10 minutes for first setup

**Installation:**
```bash
# 1. Clone repository
git clone https://github.com/your-org/anvius-uned-extension-finder.git
cd anvius-uned-extension-finder

# 2. Setup environment
cp infra/env/local.env .env

# 3. Start services
docker compose -f infra/compose.yaml up -d

# 4. Open in browser
open http://localhost:5173
```

The system will start downloading activities (5-10 minutes on first run).

**Check it's working:**
- Frontend: http://localhost:5173
- API Status: http://localhost:8080/status

## How to Use

### Searching Activities
1. Go to Activities tab
2. Enter keywords or use filters
3. Filter by price, language, format, duration
4. Click activity for full details
5. Register on UNED's official site

### Saving Searches (Login Required)
1. Create search with favorite filters
2. Click "Save Search"
3. Give it a name
4. Access anytime from your account

### Getting Notifications
1. Sign in to account
2. Go to Settings → Notifications
3. Choose frequency:
   - Immediate (instant notifications)
   - Daily digest (once per day)
   - Weekly digest (once per week)

### Understanding Activity Details
- Activity description from UNED
- Dates & time
- Prices for different participant types
- Instructor information
- Format (online/in-person/hybrid)
- Language
- Direct enrollment link

## FAQ

**Is this official UNED?**
No, this is a community search tool. UNED is not affiliated.

**Is it free?**
Yes, completely free and open source (MIT License).

**Do I need an account?**
No to browse. Yes for saved searches and notifications.

**How current is the data?**
Activities refresh every hour from UNED sources.

**Why can't I register here?**
This is a search tool only. Registration on UNED's site.

**What languages are supported?**
Spanish, English, Catalan, Valencian, Basque, Galician.

**How do I report bugs?**
Create issue on GitHub or email support@example.com

**Can I self-host it?**
Yes, it's open source. Just Docker Compose needed.

## License

This project is licensed under the **MIT License** - see [LICENSE](LICENSE) file.

## Support

- **Website:** https://example.com
- **Email:** support@example.com
- **GitHub:** Issues and Discussions
- **Social:** @UNEDActivities

---

**Last Updated:** February 8, 2026
**Version:** 1.1.3
**Status:** Stable release
**License:** MIT
