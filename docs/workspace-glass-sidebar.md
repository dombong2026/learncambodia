# Workspace glass navigation

Shared workspace sidebar only: existing WorkspaceMenu groups, URLs, permissions, logo assets and theme preference remain authoritative. Expanded desktop width 254px; collapsed desktop rail 72px, with accessible named group controls opening the original menu. The existing header toggle and local preference remain in charge. Mobile retains its original drawer, close button, overlay and focus behavior. CSS uses navy, blue and restrained gold accents, translucent layered gradients, hover elevation and reduced-motion support. No reference branding, fake notifications or decorative nonfunctional buttons are introduced. Standalone CSS and JS avoid a Vite rebuild. Production layout drift must be reconciled before release.

The expanded shell retains its pre-existing 266px content offset. Motion is enabled after initial rendering so hydration does not animate existing builder geometry. The original assessment cover stability regression test is unchanged.

## V3 refinement

Top-level Dashboard retains its existing Overview destination in all four workspaces. The desktop header's redundant toggle is visually hidden only after glass initialization; the existing controller remains the single state authority, and mobile retains its header opener. Expanded active child uses a high-contrast glass selection; its parent has a quieter section accent. The collapsed rail keeps an active group highlight. Dashboard landing routes use an accessible visually hidden title instead of the redundant visual introduction; the role action moves alongside the existing showcase status. Other pages retain their headings. Expanded 266px content offset remains unchanged.
