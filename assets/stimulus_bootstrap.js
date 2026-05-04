import { startStimulusApp } from '@symfony/stimulus-bundle';
import CatalogController from './controllers/catalog_controller.js';
import ProductController from './controllers/product_controller.js';
import CartController from './controllers/cart_controller.js';
import OrdersController from './controllers/orders_controller.js';
import ProfileController from './controllers/profile_controller.js';
import CheckoutController from './controllers/checkout_controller.js';
import ToastController from './controllers/toast_controller.js';
import MeterController from './controllers/meter_controller.js';
import InstallController from './controllers/install_controller.js';

const app = startStimulusApp();

app.register('catalog', CatalogController);
app.register('product', ProductController);
app.register('cart', CartController);
app.register('orders', OrdersController);
app.register('profile', ProfileController);
app.register('checkout', CheckoutController);
app.register('toast', ToastController);
app.register('meter', MeterController);
app.register('install', InstallController);
