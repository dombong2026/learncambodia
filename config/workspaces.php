<?php

return json_decode(<<<'JSON'
{
  "admin": [
    {
      "key": "dashboard",
      "title": "Dashboard",
      "icon": "layout",
      "items": [
        {
          "key": "overview",
          "label": "Overview",
          "route": "admin.overview"
        },
        {
          "key": "profile",
          "label": "Profile",
          "route": "admin.profile.edit"
        }
      ]
    },
    {
      "key": "advertisement",
      "title": "Advertisement",
      "icon": "megaphone",
      "items": [
        {
          "key": "internal-ads",
          "label": "Internal Ads"
        },
        {
          "key": "google-ads",
          "label": "Google Ads"
        },
        {
          "key": "facebook-ads",
          "label": "Facebook Ads"
        },
        {
          "key": "third-party-ads",
          "label": "Third Party Ads"
        }
      ]
    },
    {
      "key": "appearance",
      "title": "Appearance",
      "icon": "palette",
      "items": [
        {
          "key": "all",
          "label": "Appearance Overview",
          "route": "admin.appearance.index"
        },
        {
          "key": "header",
          "label": "Header",
          "route": "admin.appearance.edit",
          "parameters": {
            "section": "header"
          }
        },
        {
          "key": "menu",
          "label": "Menu",
          "route": "admin.appearance.edit",
          "parameters": {
            "section": "menu"
          }
        },
        {
          "key": "footer",
          "label": "Footer",
          "route": "admin.appearance.edit",
          "parameters": {
            "section": "footer"
          }
        },
        {
          "key": "typography",
          "label": "Typography",
          "route": "admin.appearance.edit",
          "parameters": {
            "section": "typography"
          }
        },
        {
          "key": "home",
          "label": "Home",
          "route": "admin.appearance.edit",
          "parameters": {
            "section": "home"
          }
        },
        {
          "key": "about",
          "label": "About",
          "route": "admin.appearance.edit",
          "parameters": {
            "section": "about"
          }
        },
        {
          "key": "contact",
          "label": "Contact",
          "route": "admin.appearance.edit",
          "parameters": {
            "section": "contact"
          }
        },
        {
          "key": "course",
          "label": "Course",
          "route": "admin.appearance.edit",
          "parameters": {
            "section": "course"
          }
        },
        {
          "key": "catalog",
          "label": "Catalog",
          "route": "admin.appearance.edit",
          "parameters": {
            "section": "catalog"
          }
        },
        {
          "key": "auth",
          "label": "Authentication",
          "route": "admin.appearance.edit",
          "parameters": {
            "section": "auth"
          }
        }
      ]
    },
    {
      "key": "language-test",
      "title": "Language Test & Qualifications",
      "icon": "graduation",
      "items": [
        {
          "key": "a1",
          "label": "CEFR A1"
        },
        {
          "key": "a2",
          "label": "CEFR A2"
        },
        {
          "key": "b1",
          "label": "CEFR B1"
        },
        {
          "key": "b2",
          "label": "CEFR B2"
        },
        {
          "key": "c1",
          "label": "CEFR C1"
        },
        {
          "key": "c2",
          "label": "CEFR C2"
        },
        {
          "key": "tesol",
          "label": "TESOL"
        },
        {
          "key": "celta",
          "label": "CELTA"
        },
        {
          "key": "delta",
          "label": "DELTA"
        },
        {
          "key": "tkt",
          "label": "TKT"
        },
        {
          "key": "teyl",
          "label": "TEYL"
        },
        {
          "key": "esl",
          "label": "ESL"
        },
        {
          "key": "efl",
          "label": "EFL"
        },
        {
          "key": "pte",
          "label": "PTE"
        },
        {
          "key": "det",
          "label": "DET"
        },
        {
          "key": "oet",
          "label": "OET"
        },
        {
          "key": "celpip",
          "label": "CELPIP"
        },
        {
          "key": "fce",
          "label": "FCE"
        },
        {
          "key": "cae",
          "label": "CAE"
        },
        {
          "key": "cpe",
          "label": "CPE"
        },
        {
          "key": "cefr",
          "label": "CEFR"
        },
        {
          "key": "tefl",
          "label": "TEFL"
        },
        {
          "key": "toeic",
          "label": "TOEIC"
        },
        {
          "key": "toefl",
          "label": "TOEFL"
        },
        {
          "key": "ielts",
          "label": "IELTS"
        }
      ]
    },
    {
      "key": "page-manager",
      "title": "Page Manager",
      "icon": "file",
      "items": [
        {
          "key": "all",
          "label": "All Pages",
          "route": "admin.editorial.index",
          "parameters": {
            "kind": "pages"
          }
        },
        {
          "key": "create",
          "label": "Create",
          "route": "admin.editorial.create",
          "parameters": {
            "kind": "pages"
          }
        },
        {
          "key": "settings",
          "label": "Settings"
        }
      ]
    },
    {
      "key": "post-manager",
      "title": "Post Manager",
      "icon": "pen",
      "items": [
        {
          "key": "all",
          "label": "All Posts",
          "route": "admin.editorial.index",
          "parameters": {
            "kind": "posts"
          }
        },
        {
          "key": "create",
          "label": "Create",
          "route": "admin.editorial.create",
          "parameters": {
            "kind": "posts"
          }
        },
        {
          "key": "category",
          "label": "Categories"
        },
        {
          "key": "tag",
          "label": "Tags"
        },
        {
          "key": "settings",
          "label": "Settings"
        }
      ]
    },
    {
      "key": "support-center",
      "title": "Support Center",
      "icon": "headphones",
      "items": [
        {
          "key": "contact",
          "label": "Contact"
        },
        {
          "key": "ticketing",
          "label": "Ticketing"
        },
        {
          "key": "live-chat",
          "label": "Live Chat"
        }
      ]
    },
    {
      "key": "policy-center",
      "title": "Policy Center",
      "icon": "shield",
      "items": [
        {
          "key": "all",
          "label": "All Policies",
          "route": "admin.editorial.index",
          "parameters": {
            "kind": "policies"
          }
        },
        {
          "key": "create",
          "label": "Create",
          "route": "admin.editorial.create",
          "parameters": {
            "kind": "policies"
          }
        },
        {
          "key": "settings",
          "label": "Settings"
        }
      ]
    },
    {
      "key": "user-manager",
      "title": "User Manager",
      "icon": "users",
      "items": [
        {
          "key": "all",
          "label": "All Users",
          "route": "admin.users.index"
        },
        {
          "key": "create",
          "label": "Create"
        },
        {
          "key": "settings",
          "label": "Settings"
        }
      ]
    },
    {
      "key": "finance-manager",
      "title": "Finance Manager",
      "icon": "wallet",
      "items": [
        {
          "key": "payment-method",
          "label": "Payment Methods"
        }
      ]
    },
    {
      "key": "system-settings",
      "title": "System Settings",
      "icon": "settings",
      "items": [
        {
          "key": "basic",
          "label": "Basic"
        },
        {
          "key": "branding",
          "label": "Branding"
        },
        {
          "key": "security",
          "label": "Security"
        },
        {
          "key": "cloud-storage",
          "label": "Cloud Storage"
        },
        {
          "key": "social-auth",
          "label": "Social Auth"
        }
      ]
    },
    {
      "key": "category",
      "title": "Category",
      "icon": "layers",
      "items": [
        {
          "key": "all",
          "label": "All Categories",
          "route": "admin.categories.index"
        },
        {
          "key": "create",
          "label": "Create Category",
          "route": "admin.categories.create"
        },
        {
          "key": "cefr",
          "label": "CEFR Levels",
          "route": "admin.cefr.index"
        }
      ]
    },
    {
      "key": "courses",
      "title": "Courses",
      "icon": "book",
      "items": [
        {
          "key": "all",
          "label": "All Courses",
          "route": "admin.courses.index"
        },
        {
          "key": "create",
          "label": "Create",
          "route": "admin.course-builder.create"
        },
        {
          "key": "instructors",
          "label": "Instructors"
        },
        {
          "key": "enrollments",
          "label": "Enrollments",
          "route": "admin.reviews.index",
          "parameters": {
            "focus": "enrollments"
          }
        }
      ]
    },
    {
      "key": "approvals",
      "title": "Review & Approvals",
      "icon": "shield",
      "items": [
        {
          "key": "course-reviews",
          "label": "Course Reviews",
          "route": "admin.reviews.index",
          "parameters": {
            "focus": "courses"
          }
        },
        {
          "key": "appearance-reviews",
          "label": "Appearance Reviews",
          "route": "admin.reviews.index",
          "parameters": {
            "focus": "appearance"
          }
        },
        {
          "key": "enrollment-reviews",
          "label": "Enrollment Reviews",
          "route": "admin.reviews.index",
          "parameters": {
            "focus": "enrollments"
          }
        }
      ]
    }
  ],
  "student": [
    {
      "key": "dashboard",
      "title": "Dashboard",
      "icon": "layout",
      "items": [
        {
          "key": "overview",
          "label": "Overview",
          "route": "account.overview"
        },
        {
          "key": "profile",
          "label": "Profile",
          "route": "account.profile.edit"
        }
      ]
    },
    {
      "key": "learning",
      "title": "My Learning",
      "icon": "book",
      "items": [
        {
          "key": "categories",
          "label": "Course Catalog",
          "route": "account.categories"
        },
        {
          "key": "courses",
          "label": "My Enrollments",
          "route": "account.enrollments.index"
        },
        {
          "key": "progress",
          "label": "Learning Progress"
        },
        {
          "key": "cefr",
          "label": "CEFR Guide",
          "route": "account.cefr"
        },
        {
          "key": "course-catalog",
          "label": "Available Courses",
          "route": "account.courses.index"
        }
      ]
    },
    {
      "key": "assessments",
      "title": "Assessments",
      "icon": "graduation",
      "items": [
        {
          "key": "placement",
          "label": "Placement Test"
        },
        {
          "key": "results",
          "label": "My Results"
        }
      ]
    },
    {
      "key": "support",
      "title": "Student Support",
      "icon": "headphones",
      "items": [
        {
          "key": "help",
          "label": "Help Center",
          "route": "account.help"
        },
        {
          "key": "tickets",
          "label": "My Tickets"
        },
        {
          "key": "settings",
          "label": "Account Settings",
          "route": "account.profile.edit"
        }
      ]
    }
  ],
  "developer": [
    {
      "key": "dashboard",
      "title": "Dashboard",
      "icon": "layout",
      "items": [
        {
          "key": "overview",
          "label": "Overview",
          "route": "developer.overview"
        },
        {
          "key": "profile",
          "label": "Profile",
          "route": "developer.profile.edit"
        }
      ]
    },
    {
      "key": "api-center",
      "title": "API Center",
      "icon": "code",
      "items": [
        {
          "key": "endpoints",
          "label": "API Endpoints",
          "route": "developer.apis"
        },
        {
          "key": "docs",
          "label": "Documentation",
          "route": "developer.apis"
        },
        {
          "key": "catalog",
          "label": "Catalog API",
          "route": "developer.apis"
        }
      ]
    },
    {
      "key": "credentials",
      "title": "API Credentials",
      "icon": "key",
      "items": [
        {
          "key": "tokens",
          "label": "Access Tokens",
          "route": "developer.tokens.index"
        },
        {
          "key": "new",
          "label": "Create Token",
          "route": "developer.tokens.index"
        }
      ]
    },
    {
      "key": "operations",
      "title": "Operations",
      "icon": "activity",
      "items": [
        {
          "key": "usage",
          "label": "Usage & Limits",
          "route": "developer.usage"
        },
        {
          "key": "security",
          "label": "Security",
          "route": "developer.profile.edit"
        }
      ]
    }
  ]
}
JSON, true, 512, JSON_THROW_ON_ERROR);
