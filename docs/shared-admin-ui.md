# Shared ContactPanda admin controls

Panda Seasons uses the family-wide `ColorPicker` and `ImagePicker` contract from ContactPanda Basic.

- Canonical JavaScript API: `ContactPandaAdminUI.enhanceColorPicker()` and `ContactPandaAdminUI.createImagePicker()`.
- Canonical control styles: `contactpanda-admin-ui.css`.
- If ContactPanda Basic is installed, Seasons loads its central JavaScript module.
- Standalone editions bundle the same module and styles, so the controls remain available without Basic.
- Product-specific styles must not redefine the internal `contactpanda-unified-color*` or `contactpanda-image-picker*` geometry.
- Theme palettes include WordPress global colors and supported Avada, Elementor and Divi colors. The globe remains the entry point to that palette.

When the shared module changes, synchronize the bundled standalone copy before releasing an affected product.
