# Innovopedia Brief WordPress Plugin

**Deployment Domains:**
- **Backend API:** https://api.innovopedia.com
- **Frontend (WordPress site):** https://innovopedia.com

:warning: **Important:** Set the API base URL in the plugin settings to `https://api.innovopedia.com` for production deployments. All plugin features rely on correct API connectivity.

## CORS Configuration

Your backend at `https://api.innovopedia.com` must allow requests from your frontend domain `https://innovopedia.com`.

**Example for FastAPI:**
```python
from fastapi.middleware.cors import CORSMiddleware

app.add_middleware(
    CORSMiddleware,
    allow_origins=["https://innovopedia.com"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)
```

**Example for Express.js:**
```js
const cors = require('cors');
app.use(cors({
  origin: 'https://innovopedia.com',
  credentials: true
}));
```

**Example for Nginx (if proxying):**
```nginx
add_header 'Access-Control-Allow-Origin' 'https://innovopedia.com';
add_header 'Access-Control-Allow-Credentials' 'true';
add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS, PUT, DELETE';
add_header 'Access-Control-Allow-Headers' 'Origin, Content-Type, Accept, Authorization';
```

> ⚠️ Make sure to restrict `allow_origins` or `Access-Control-Allow-Origin` to your production frontend only for security.


A modern, AI-powered news briefing popup for WordPress, with a beautiful, accessible UI and a powerful admin dashboard.

## Features
- **Shortcode**: `[innovopedia-brief]` renders a pill-shaped "Your Briefing" button anywhere.
- **Modern Popup**: Right-side, glassmorphism-styled, fully responsive popup/slide.
- **API-Powered**: Fetches stories, topics, audio, analytics, and more from your Innovopedia backend.
- **Interactive**: Topic filtering, feedback form, audio playback, and AI-generated content notice.
- **Admin Dashboard**: View analytics, export feedback/questions as CSV, trigger trend analysis, and configure settings.
- **Accessibility**: Keyboard navigation, ARIA labels, ESC to close, and responsive design.
- **No Theme Conflicts**: All overlays appended to `<body>`, no floating buttons.

## Installation
1. **Copy Plugin**: Place the `frontend` folder (or its contents) into your WordPress `wp-content/plugins/` directory. Rename the folder to `innovopedia-brief` if needed.
2. **Activate**: Go to WP Admin > Plugins and activate "Innovopedia Brief".

## Configuration
1. **API Base URL**: Go to WP Admin > Innovopedia Brief > Settings. Enter the base URL of your Innovopedia backend (e.g., `https://api.innovopedia.com`).
2. **Save Settings**.

## Usage
- **Add Briefing Button**: Place `[innovopedia-brief]` in any page or post.
- **Interact**: Click the button to open the popup, view stories, filter by topic, submit feedback, or play audio.
- **Admin Dashboard**: Go to WP Admin > Innovopedia Brief for analytics, CSV exports, and trend analysis.

## API Endpoints Used
- `/get-briefing-advanced` — Fetches stories, topics, greeting, and intro
- `/submit-feedback` — Receives feedback form submissions (POST)
- `/get-briefing-audio` — Returns audio for the briefing
- `/admin-stats` — Analytics/stats for dashboard
- `/admin-feedback` — Feedback CSV
- `/admin-questions` — Questions CSV
- `/analyze-trend` — Triggers trend analysis

> **Note:** All endpoints are prefixed by the API base URL set in plugin settings.

## Customization & Advanced UI/UX
- The plugin uses modern CSS for glassmorphism, pill buttons, and responsive layouts.
- Font Awesome is included for icons.
- For further customization (dark mode, transitions, toast notifications, etc.), edit `assets/brief.css` and `assets/brief.js`.
- All popup/overlay elements are appended to `<body>` for maximum compatibility.

## Accessibility
- Full keyboard navigation
- ARIA roles and labels
- ESC to close popup

## Troubleshooting
- **Popup not showing?** Ensure the API base URL is set and the backend is reachable.
- **No data?** Check your backend endpoints and CORS headers.
- **CSV download not working?** Ensure the backend returns valid CSV and the API base URL is correct.

## Support & Contributions
For feature requests or issues, please contact the developer or open a pull request.

---

© 2025 Innovopedia. All rights reserved.
