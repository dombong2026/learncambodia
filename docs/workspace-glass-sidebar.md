# Workspace glass navigation

Shared workspace sidebar only: existing WorkspaceMenu groups, URLs, permissions, logo assets and theme preference remain authoritative. Expanded desktop width 254px; collapsed desktop rail 72px, with accessible named group controls opening the original menu. The existing header toggle and local preference remain in charge. Mobile retains its original drawer, close button, overlay and focus behavior. CSS uses navy, blue and restrained gold accents, translucent layered gradients, hover elevation and reduced-motion support. No reference branding, fake notifications or decorative nonfunctional buttons are introduced. Standalone CSS and JS avoid a Vite rebuild. Production layout drift must be reconciled before release.

The expanded shell retains its pre-existing 266px content offset. Motion is enabled after initial rendering so hydration does not animate existing builder geometry. The original assessment cover stability regression test is unchanged.
