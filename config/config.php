<?php
declare(strict_types=1);

/**
 * Central application settings. Values that differ by environment belong in
 * config/local.php, which is intentionally excluded from version control.
 */
const APP_NAME = 'Campus Lost and Found';
const APP_ENV = 'development';
const APP_BASE_PATH = '';

const PICKUP_LOCATION = 'Student Affairs Office, Administration Building';
const MAX_UPLOAD_BYTES = 2_097_152;
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png'];
const RESULTS_PER_PAGE = 20;
const MAX_REJECTED_CLAIMS = 3;
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_MINUTES = 15;
const SESSION_IDLE_MINUTES = 30;
const MIN_PASSWORD_LENGTH = 8;

const CATEGORIES = [
    'Bag or Luggage', 'Wallet or Purse', 'ID or Bank Card', 'Keys',
    'Phone', 'Laptop or Tablet', 'Other Electronics', 'Clothing',
    'Books or Stationery', 'Bottle or Container', 'Jewellery or Accessory',
    'Umbrella', 'Other',
];

const LOCATIONS = [
    'Library', 'Main Lecture Building', 'Science Building',
    'Engineering Building', 'Cafeteria', 'Sports Complex', 'Dormitory Area',
    'Car Park', 'Administration Building', 'Outdoor or Walkway', 'Other',
];

const COLOURS = [
    'Black', 'White', 'Grey', 'Brown', 'Red', 'Orange', 'Yellow', 'Green',
    'Blue', 'Purple', 'Pink', 'Multicolour', 'Other',
];
