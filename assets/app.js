import '@hotwired/turbo';
import './controllers/csrf_protection_controller.js';
import './stimulus_bootstrap.js';
import './styles/app.css';

document.addEventListener('turbo:frame-missing', (event) => {
    event.preventDefault();
    event.detail.visit(event.detail.response);
});
