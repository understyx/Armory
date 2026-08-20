// assets/app.js
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// Import Bootstrap's JavaScript (and its dependencies)
// This will make Bootstrap's JS components (like dropdowns, modals) available
import 'bootstrap';

// You might also need to import Bootstrap's CSS directly here if not handled otherwise
// import 'bootstrap/dist/css/bootstrap.min.css';

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';

// start the Stimulus application
import './bootstrap.js';