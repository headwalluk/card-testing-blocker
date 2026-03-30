<?php
/**
 * Plugin constants.
 *
 * @since 0.1.0
 *
 * @package Card_Testing_Blocker
 */

namespace Card_Testing_Blocker;

defined( 'ABSPATH' ) || die();

// --- Honeypot products ---------------------------------------------------------

const META_HONEYPOT        = '_ctb_honeypot';
const DEF_HONEYPOT_COUNT   = 20;
const DEF_HONEYPOT_MIN_PRICE = 1.00;

// --- Threat scoring ------------------------------------------------------------

const DEF_THREAT_THRESHOLD    = 50;
const DEF_SCORE_HONEYPOT      = 100;
const DEF_SCORE_EMPTY_SEARCH  = 30;
const DEF_SCORE_HTTP_PROTOCOL = 10;

// --- Transients ----------------------------------------------------------------

const TRANSIENT_PREFIX_EMPTY_SEARCH = 'ctb_empty_search_';
const DEF_EMPTY_SEARCH_TTL         = HOUR_IN_SECONDS;

// --- Options -------------------------------------------------------------------

const OPT_SETTINGS = 'ctb_settings';

// --- HTTP protocol detection ---------------------------------------------------

const HTTP_VERSION_HEADER  = 'HTTP_X_CTB_HTTP_VERSION';
const DEF_EXPECTED_PROTOCOL = 'HTTP/2.0';
