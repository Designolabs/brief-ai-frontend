# Innovopedia Brief WordPress Plugin

**Deployment Domains:**
- **Backend API:** https://api.innovopedia.com
- **Frontend (WordPress site):** https://innovopedia.com

A modern, AI-powered news briefing popup for WordPress, with a beautiful, accessible UI and a powerful admin dashboard.

---

## 🚀 Features

- **Shortcode**: `[innovopedia-brief]` renders a pill-shaped "Your Briefing" button.
- **Modern Popup**: A glassmorphism-styled, fully responsive slide-in popup.
- **API-Powered**: Fetches stories, topics, audio, analytics, and more from your Innovopedia backend.
- **Interactive**: Topic filtering, "Up Next" and "Popular Stories" sections, feedback form, and audio playback.
- **AI Training**: Trigger AI training on your website content directly from the admin dashboard.
- **Customizable Appearance**: Control primary/secondary colors and border-radius from the settings page.
- **Admin Dashboard**:
    - View key analytics.
    - Monitor API health.
    - View feedback, questions, and content moderation lists.
    - Export feedback/questions as CSV.
    - Trigger manual or scheduled trend analysis.
- **Accessibility**: Keyboard navigation, ARIA labels, and responsive design.

---

## 🔧 Installation & Configuration

1.  **Copy Plugin**: Place the `frontend` folder (or its contents) into your WordPress `wp-content/plugins/` directory. Rename the folder to `innovopedia-brief` if needed.
2.  **Activate**: Go to WP Admin > Plugins and activate "Innovopedia Brief".
3.  **Configure Settings**: Navigate to **Innovopedia Brief > Settings** in your WordPress admin menu.
    - **API Base URL**: Enter the base URL of your Innovopedia backend (e.g., `https://api.innovopedia.com`).
    - **API Key**: Enter the secret API key required to authenticate with your backend.
    - **Appearance**: Customize the primary color, secondary color, and border-radius to match your site's theme.
4.  **Save Settings**.

---

## Usage

-   **Add Briefing Button**: Place the `[innovopedia-brief]` shortcode in any page, post, or widget.
-   **Interact**: Click the button to open the popup, view stories, filter by topic, submit feedback, or play audio.
-   **Admin Dashboard**: Go to **WP Admin > Innovopedia Brief** for analytics, data exports, AI training, and more.

---

## ⚙️ API Endpoints Used

All endpoints are prefixed by the **API Base URL** set in plugin settings and require an `X-API-Key` header for authentication.

-   **`GET /get-briefing-advanced`**: Fetches stories, topics, greeting, intro, up next, and popular stories.
-   **`POST /submit-feedback`**: Submits user feedback.
-   **`GET /get-briefing-audio`**: Returns audio for the briefing.
-   **`GET /admin-stats`**: Analytics for the dashboard.
-   **`GET /admin-feedback`**: Returns feedback data as a raw CSV file.
-   **`GET /admin-questions`**: Returns questions data as a raw CSV file.
-   **`GET /admin-feedback-list`**: Returns a JSON list of feedback for the interactive viewer.
-   **`GET /admin-questions-list`**: Returns a JSON list of questions for the interactive viewer.
-   **`GET /admin-moderation`**: Returns a JSON list of content with moderation status.
-   **`GET /analyze-trend`**: Triggers a manual trend analysis.
-   **`POST /admin-train-ai`**: Sends a command to the backend to initiate AI training.
-   **`GET /health`**: Checks the health of the API.

> **Note:** Your backend at `https://api.innovopedia.com` must have CORS properly configured to allow requests from your frontend domain `https://innovopedia.com`.

---

© 2025 Innovopedia. All rights reserved.
