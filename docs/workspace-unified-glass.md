# Unified workspace glass V4

The shared account shell receives a matching pearl/ice-blue light canvas and midnight/indigo dark canvas, with translucent sidebar and header surfaces derived from the same palette. Dashboard landing cards receive frosted glass, translucent fills, edge reflections and one 4.4-second background-only sweep on entry. The navy feature card retains its white/gold text and dark emphasis. Reduced Motion and existing Data Saver disable the new animation. There is no repeated animation, image download, layout movement, new JavaScript, data change or hover-rule replacement.

The original V3 sidebar stylesheet, controller, routes and dashboard markup remain unchanged. The new stylesheet is loaded after workspace styles and V3, and applies dashboard glass cards only when the existing compact landing heading is present. Other account pages share the canvas and header, but forms, inputs, tables and public pages are not restyled as glass cards. Existing card hover/focus properties remain in charge; idle border/shadow styling yields to them. The design implements the mockup's palette and reflective light with CSS, without literal ornamental flowers or neon decorations.

Production: install only the new CSS and the shared layout's additional stylesheet link, retaining all local V3 compatibility modifications. No migrations or Vite rebuild required.
