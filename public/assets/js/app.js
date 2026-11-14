/** Browser entry point: imported modules attach behavior to the HTML rendered by PHP. */
import { initUi } from './ui.js';
import { initAuth } from './auth.js';
import { initGallery } from './gallery.js';
import { initForms } from './forms.js';
import { initSettings } from './settings.js';
import { initPhoto } from './photo.js';
import { initEditor } from './editor.js';

initUi();
initAuth();
initGallery();
initForms();
initSettings();
initPhoto();
initEditor();
