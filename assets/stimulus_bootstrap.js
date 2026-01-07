import { startStimulusApp } from '@symfony/stimulus-bundle';
import FlashController from './controllers/flash_controller.js';
import ThemeController from './controllers/theme_controller.js';

const app = startStimulusApp();
app.register('flash', FlashController);
app.register('theme', ThemeController);
