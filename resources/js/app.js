// Alpine.js is provided by Livewire/Flux
import './echo';
import { hbRoom } from './watch-party.js';

// Register the Hyperbeam "Sala" control-bar component. The heavy @hyperbeam/web
// SDK is dynamically imported inside hbRoom.init(), so it only loads on that page.
document.addEventListener('alpine:init', () => window.Alpine.data('hbRoom', hbRoom));
