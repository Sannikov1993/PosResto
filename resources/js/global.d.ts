/**
 * Global type declarations for the MenuLab application.
 */

// Yandex Maps API
interface Window {
    ymaps: any;
    Echo: any;
    Pusher: any;
    axios: any;
    _: any;
}

// Declare global ymaps namespace
declare const ymaps: any;

// Laravel Echo / Pusher
declare const Echo: any;
declare const Pusher: any;
