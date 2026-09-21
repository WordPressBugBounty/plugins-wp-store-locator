<?php
/**
 * Regions, countries, languages and restrictions.
 *
 * Part of the wpsl-functions API ( see wpsl-functions.php ).
 *
 * @package WPSL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Country name / iso code list.
 *
 * @since   3.0.0
 * @return  array $region_list
 */
function wpsl_get_regions() {
    $region_list =  [
        __('Select your region', 'wp-store-locator')               => '',
        __('Afghanistan', 'wp-store-locator')                      => 'af',
        __('Albania', 'wp-store-locator')                          => 'al',
        __('Algeria', 'wp-store-locator')                          => 'dz',
        __('American Samoa', 'wp-store-locator')                   => 'as',
        __('Andorra', 'wp-store-locator')                          => 'ad',
        __('Angola', 'wp-store-locator')                           => 'ao',
        __('Anguilla', 'wp-store-locator')                         => 'ai',
        __('Antarctica', 'wp-store-locator')                       => 'aq',
        __('Antigua &amp; Barbuda', 'wp-store-locator')            => 'ag',
        __('Argentina', 'wp-store-locator')                        => 'ar',
        __('Armenia', 'wp-store-locator')                          => 'am',
        __('Aruba', 'wp-store-locator')                            => 'aw',
        __('Ascension Island', 'wp-store-locator')                 => 'ac',
        __('Australia', 'wp-store-locator')                        => 'au',
        __('Austria', 'wp-store-locator')                          => 'at',
        __('Azerbaijan', 'wp-store-locator')                       => 'az',
        __('Bahamas', 'wp-store-locator')                          => 'bs',
        __('Bahrain', 'wp-store-locator')                          => 'bh',
        __('Bangladesh', 'wp-store-locator')                       => 'bd',
        __('Barbados', 'wp-store-locator')                         => 'bb',
        __('Belarus', 'wp-store-locator')                          => 'by',
        __('Belgium', 'wp-store-locator')                          => 'be',
        __('Belize', 'wp-store-locator')                           => 'bz',
        __('Benin', 'wp-store-locator')                            => 'bj',
        __('Bermuda', 'wp-store-locator')                          => 'bm',
        __('Bhutan', 'wp-store-locator')                           => 'bt',
        __('Bolivia', 'wp-store-locator')                          => 'bo',
        __('Bosnia &amp; Herzegovina', 'wp-store-locator')         => 'ba',
        __('Botswana', 'wp-store-locator')                         => 'bw',
        __('Bouvet Island', 'wp-store-locator')                    => 'bv',
        __('Brazil', 'wp-store-locator')                           => 'br',
        __('British Indian Ocean Territory', 'wp-store-locator')   => 'io',
        __('British Virgin Islands', 'wp-store-locator')           => 'vg',
        __('Brunei', 'wp-store-locator')                           => 'bn',
        __('Bulgaria', 'wp-store-locator')                         => 'bg',
        __('Burkina Faso', 'wp-store-locator')                     => 'bf',
        __('Burundi', 'wp-store-locator')                          => 'bi',
        __('Cambodia', 'wp-store-locator')                         => 'kh',
        __('Cameroon', 'wp-store-locator')                         => 'cm',
        __('Canada', 'wp-store-locator')                           => 'ca',
        __('Canary Islands', 'wp-store-locator')                   => 'ic',
        __('Cape Verde', 'wp-store-locator')                       => 'cv',
        __('Caribbean Netherlands', 'wp-store-locator')            => 'bq',
        __('Cayman Islands', 'wp-store-locator')                   => 'ky',
        __('Central African Republic', 'wp-store-locator')         => 'cf',
        __('Ceuta and Melilla', 'wp-store-locator')                => 'ea',
        __('Chad', 'wp-store-locator')                             => 'td',
        __('Chile', 'wp-store-locator')                            => 'cl',
        __('China', 'wp-store-locator')                            => 'cn',
        __('Christmas Island', 'wp-store-locator')                 => 'cx',
        __('Clipperton Island', 'wp-store-locator')                => 'cp',
        __('Cocos (Keeling) Islands', 'wp-store-locator')          => 'cc',
        __('Colombia', 'wp-store-locator')                         => 'co',
        __('Comoros', 'wp-store-locator')                          => 'km',
        __('Congo - Brazzaville', 'wp-store-locator')              => 'cg',
        __('Congo - Kinshasa', 'wp-store-locator')                 => 'cd',
        __('Cook Islands', 'wp-store-locator')                     => 'ck',
        __('Costa Rica', 'wp-store-locator')                       => 'cr',
        __('Croatia', 'wp-store-locator')                          => 'hr',
        __('Cuba', 'wp-store-locator')                             => 'cu',
        __('Curaçao', 'wp-store-locator')                          => 'cw',
        __('Cyprus', 'wp-store-locator')                           => 'cy',
        __('Czech Republic', 'wp-store-locator')                   => 'cz',
        __('Côte d\'Ivoire', 'wp-store-locator')                   => 'ci',
        __('Denmark', 'wp-store-locator')                          => 'dk',
        __('Djibouti', 'wp-store-locator')                         => 'dj',
        __('Dominica', 'wp-store-locator')                         => 'dm',
        __('Dominican Republic', 'wp-store-locator')               => 'do',
        __('Ecuador', 'wp-store-locator')                          => 'ec',
        __('Egypt', 'wp-store-locator')                            => 'eg',
        __('El Salvador', 'wp-store-locator')                      => 'sv',
        __('Equatorial Guinea', 'wp-store-locator')                => 'gq',
        __('Eritrea', 'wp-store-locator')                          => 'er',
        __('Estonia', 'wp-store-locator')                          => 'ee',
        __('Eswatini', 'wp-store-locator')                         => 'sz',
        __('Ethiopia', 'wp-store-locator')                         => 'et',
        __('Falkland Islands (Islas Malvinas)', 'wp-store-locator') => 'fk',
        __('Faroe Islands', 'wp-store-locator')                    => 'fo',
        __('Fiji', 'wp-store-locator')                             => 'fj',
        __('Finland', 'wp-store-locator')                          => 'fi',
        __('France', 'wp-store-locator')                           => 'fr',
        __('French Guiana', 'wp-store-locator')                    => 'gf',
        __('French Polynesia', 'wp-store-locator')                 => 'pf',
        __('French Southern Territories', 'wp-store-locator')      => 'tf',
        __('Gabon', 'wp-store-locator')                            => 'ga',
        __('Gambia', 'wp-store-locator')                           => 'gm',
        __('Georgia', 'wp-store-locator')                          => 'ge',
        __('Germany', 'wp-store-locator')                          => 'de',
        __('Ghana', 'wp-store-locator')                            => 'gh',
        __('Gibraltar', 'wp-store-locator')                        => 'gi',
        __('Greece', 'wp-store-locator')                           => 'gr',
        __('Greenland', 'wp-store-locator')                        => 'gl',
        __('Grenada', 'wp-store-locator')                          => 'gd',
        __('Guam', 'wp-store-locator')                             => 'gu',
        __('Guadeloupe', 'wp-store-locator')                       => 'gp',
        __('Guam', 'wp-store-locator')                             => 'gu',
        __('Guatemala', 'wp-store-locator')                        => 'gt',
        __('Guernsey', 'wp-store-locator')                         => 'gg',
        __('Guinea', 'wp-store-locator')                           => 'gn',
        __('Guinea-Bissau', 'wp-store-locator')                    => 'gw',
        __('Guyana', 'wp-store-locator')                           => 'gy',
        __('Haiti', 'wp-store-locator')                            => 'ht',
        __('Heard &amp; McDonald Islands', 'wp-store-locator')     => 'hm',
        __('Honduras', 'wp-store-locator')                         => 'hn',
        __('Hong Kong', 'wp-store-locator')                        => 'hk',
        __('Hungary', 'wp-store-locator')                          => 'hu',
        __('Iceland', 'wp-store-locator')                          => 'is',
        __('India', 'wp-store-locator')                            => 'in',
        __('Indonesia', 'wp-store-locator')                        => 'id',
        __('Iran', 'wp-store-locator')                             => 'ir',
        __('Iraq', 'wp-store-locator')                             => 'iq',
        __('Ireland', 'wp-store-locator')                          => 'ie',
        __('Isle of Man', 'wp-store-locator')                      => 'im',
        __('Israel', 'wp-store-locator')                           => 'il',
        __('Italy', 'wp-store-locator')                            => 'it',
        __('Jamaica', 'wp-store-locator')                          => 'jm',
        __('Japan', 'wp-store-locator')                            => 'jp',
        __('Jersey', 'wp-store-locator')                           => 'je',
        __('Jordan', 'wp-store-locator')                           => 'jo',
        __('Kazakhstan', 'wp-store-locator')                       => 'kz',
        __('Kenya', 'wp-store-locator')                            => 'ke',
        __('Kiribati', 'wp-store-locator')                         => 'ki',
        __('Kosovo', 'wp-store-locator')                           => 'xk',
        __('Kuwait', 'wp-store-locator')                           => 'kw',
        __('Kyrgyzstan', 'wp-store-locator')                       => 'kg',
        __('Laos', 'wp-store-locator')                             => 'la',
        __('Latvia', 'wp-store-locator')                           => 'lv',
        __('Lebanon', 'wp-store-locator')                          => 'lb',
        __('Lesotho', 'wp-store-locator')                          => 'ls',
        __('Liberia', 'wp-store-locator')                          => 'lr',
        __('Libya', 'wp-store-locator')                            => 'ly',
        __('Liechtenstein', 'wp-store-locator')                    => 'li',
        __('Lithuania', 'wp-store-locator')                        => 'lt',
        __('Luxembourg', 'wp-store-locator')                       => 'lu',
        __('Macau', 'wp-store-locator')                            => 'mo',
        __('Madagascar', 'wp-store-locator')                       => 'mg',
        __('Malawi', 'wp-store-locator')                           => 'mw',
        __('Malaysia ', 'wp-store-locator')                        => 'my',
        __('Maldives ', 'wp-store-locator')                        => 'mv',
        __('Mali', 'wp-store-locator')                             => 'ml',
        __('Malta', 'wp-store-locator')                            => 'mt',
        __('Marshall Islands', 'wp-store-locator')                 => 'mh',
        __('Martinique', 'wp-store-locator')                       => 'mq',
        __('Mauritania', 'wp-store-locator')                       => 'mr',
        __('Mauritius', 'wp-store-locator')                        => 'mu',
        __('Mayotte', 'wp-store-locator')                          => 'yt',
        __('Mexico', 'wp-store-locator')                           => 'mx',
        __('Micronesia', 'wp-store-locator')                       => 'fm',
        __('Moldova', 'wp-store-locator')                          => 'md',
        __('Monaco' , 'wp-store-locator')                           => 'mc',
        __('Mongolia', 'wp-store-locator')                         => 'mn',
        __('Montenegro', 'wp-store-locator')                       => 'me',
        __('Montserrat', 'wp-store-locator')                       => 'ms',
        __('Morocco', 'wp-store-locator')                          => 'ma',
        __('Mozambique', 'wp-store-locator')                       => 'mz',
        __('Myanmar (Burma)', 'wp-store-locator')                  => 'mm',
        __('Namibia', 'wp-store-locator')                          => 'na',
        __('Nauru', 'wp-store-locator')                            => 'nr',
        __('Nepal', 'wp-store-locator')                            => 'np',
        __('Netherlands', 'wp-store-locator')                      => 'nl',
        __('New Caledonia', 'wp-store-locator')                    => 'nc',
        __('New Zealand', 'wp-store-locator')                      => 'nz',
        __('Nicaragua', 'wp-store-locator')                        => 'ni',
        __('Niger', 'wp-store-locator')                            => 'ne',
        __('Nigeria', 'wp-store-locator')                          => 'ng',
        __('Niue', 'wp-store-locator')                             => 'nu',
        __('Norfolk Island', 'wp-store-locator')                   => 'nf',
        __('North Korea', 'wp-store-locator')                      => 'kp',
        __('North Macedonia', 'wp-store-locator')                  => 'mk',
        __('Northern Mariana Islands', 'wp-store-locator')         => 'mp',
        __('Norway', 'wp-store-locator')                           => 'no',
        __('Oman', 'wp-store-locator')                             => 'om',
        __('Pakistan', 'wp-store-locator')                         => 'pk',
        __('Palau', 'wp-store-locator')                            => 'pw',
        __('Palestine', 'wp-store-locator')                        => 'ps',
        __('Panama' , 'wp-store-locator')                           => 'pa',
        __('Papua New Guinea', 'wp-store-locator')                 => 'pg',
        __('Paraguay' , 'wp-store-locator')                         => 'py',
        __('Peru', 'wp-store-locator')                             => 'pe',
        __('Philippines', 'wp-store-locator')                      => 'ph',
        __('Pitcairn Islands', 'wp-store-locator')                 => 'pn',
        __('Poland', 'wp-store-locator')                           => 'pl',
        __('Portugal', 'wp-store-locator')                         => 'pt',
        __('Puerto Rico', 'wp-store-locator')                      => 'pr',
        __('Qatar', 'wp-store-locator')                            => 'qa',
        __('Romania', 'wp-store-locator')                          => 'ro',
        __('Russia', 'wp-store-locator')                           => 'ru',
        __('Rwanda', 'wp-store-locator')                           => 'rw',
        __('Reunion', 'wp-store-locator')                          => 're',
        __('Samoa', 'wp-store-locator')                            => 'ws',
        __('San Marino', 'wp-store-locator')                       => 'sm',
        __('Saudi Arabia', 'wp-store-locator')                     => 'sa',
        __('Senegal', 'wp-store-locator')                          => 'sn',
        __('Serbia', 'wp-store-locator')                           => 'rs',
        __('Seychelles', 'wp-store-locator')                       => 'sc',
        __('Sierra Leone', 'wp-store-locator')                     => 'sl',
        __('Singapore', 'wp-store-locator')                        => 'sg',
        __('Sint Maarten', 'wp-store-locator')                     => 'sx',
        __('Slovakia', 'wp-store-locator')                         => 'sk',
        __('Slovenia', 'wp-store-locator')                         => 'si',
        __('Solomon Islands', 'wp-store-locator')                  => 'sb',
        __('Somalia', 'wp-store-locator')                          => 'so',
        __('South Africa', 'wp-store-locator')                     => 'za',
        __('South Georgia &amp; South Sandwich Islands', 'wp-store-locator') => 'gs',
        __('South Korea', 'wp-store-locator')                      => 'kr',
        __('South Sudan', 'wp-store-locator')                      => 'ss',
        __('Spain', 'wp-store-locator')                            => 'es',
        __('Sri Lanka', 'wp-store-locator')                        => 'lk',
        __('St. Barthélemy', 'wp-store-locator')                   => 'bl',
        __('St. Helena', 'wp-store-locator')                       => 'sh',
        __('St. Kitts &amp; Nevis', 'wp-store-locator')            => 'kn',
        __('St. Lucia', 'wp-store-locator')                        => 'lc',
        __('St. Martin', 'wp-store-locator')                       => 'mf',
        __('St. Pierre &amp; Miquelon', 'wp-store-locator')        => 'pm',
        __('St. Vincent &amp; Grenadines', 'wp-store-locator')     => 'vc',
        __('Sudan', 'wp-store-locator')                            => 'sd',
        __('Suriname', 'wp-store-locator')                         => 'sr',
        __('Svalbard &amp; Jan Mayen', 'wp-store-locator')         => 'sj',
        __('Sweden', 'wp-store-locator')                           => 'se',
        __('Switzerland', 'wp-store-locator')                      => 'ch',
        __('Syria', 'wp-store-locator')                            => 'sy',
        __('São Tomé &amp; Príncipe', 'wp-store-locator')          => 'st',
        __('Taiwan', 'wp-store-locator')                           => 'tw',
        __('Tajikistan', 'wp-store-locator')                       => 'tj',
        __('Tanzania', 'wp-store-locator')                         => 'tz',
        __('Thailand', 'wp-store-locator')                         => 'th',
        __('Timor-Leste', 'wp-store-locator')                      => 'tl',
        __('Tokelau' , 'wp-store-locator')                          => 'tk',
        __('Togo', 'wp-store-locator')                             => 'tg',
        __('Tokelau' , 'wp-store-locator')                          => 'tk',
        __('Tonga', 'wp-store-locator')                            => 'to',
        __('Trinidad &amp; Tobago', 'wp-store-locator')              => 'tt',
        __('Tristan da Cunha', 'wp-store-locator')                 => 'ta',
        __('Tunisia', 'wp-store-locator')                          => 'tn',
        __('Turkey', 'wp-store-locator')                           => 'tr',
        __('Turkmenistan', 'wp-store-locator')                     => 'tm',
        __('Turks and Caicos Islands', 'wp-store-locator')         => 'tc',
        __('Tuvalu', 'wp-store-locator')                           => 'tv',
        __('U.S. Outlying Islands', 'wp-store-locator')            => 'um',
        __('U.S. Virgin Islands', 'wp-store-locator')              => 'vi',
        __('Uganda', 'wp-store-locator')                           => 'ug',
        __('Ukraine', 'wp-store-locator')                          => 'ua',
        __('United Arab Emirates', 'wp-store-locator')             => 'ae',
        __('United Kingdom', 'wp-store-locator')                   => 'gb',
        __('United States', 'wp-store-locator')                    => 'us',
        __('Uruguay', 'wp-store-locator')                          => 'uy',
        __('Uzbekistan', 'wp-store-locator')                       => 'uz',
        __('Vanuatu', 'wp-store-locator')                          => 'vu',
        __('Vatican City', 'wp-store-locator')                     => 'va',
        __('Venezuela', 'wp-store-locator')                        => 've',
        __('Vietnam', 'wp-store-locator')                          => 'vn',
        __('Wallis Futuna', 'wp-store-locator')                    => 'wf',
        __('Western Sahara', 'wp-store-locator')                   => 'eh',
        __('Yemen', 'wp-store-locator')                            => 'ye',
        __('Zambia' , 'wp-store-locator')                           => 'zm',
        __('Zimbabwe', 'wp-store-locator')                         => 'zw',
        __('Åland Islands', 'wp-store-locator')                    => 'ax'
    ];

    return $region_list;
}

/**
 * Check if the active map provider
 * supports restricting the API request
 * to multiple countries.
 *
 * @since   3.0.0
 * @return  bool
 */
function wpsl_has_multi_region_restrictions() {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_group( 'api' );

    return in_array( $wpsl_settings['active_map_service'], [ 'mapbox', 'osm' ] );
}

/**
 * Get the list of available languages
 * based on the passed type.
 *
 * @since   3.0.0
 * @param   string $type
 * @return  array  $api_option_list List of language restrictions for the passed type
 */
function wpsl_language_restrictions( $type ) {
    $restrictions = [
        'all_languages' => [
            esc_html__('Select your language', 'wp-store-locator')    => '',
            esc_html__('English', 'wp-store-locator')                 => 'en',
            esc_html__('Afrikaans', 'wp-store-locator')               => 'af',
            esc_html__('Albanian', 'wp-store-locator')                => 'sq',
            esc_html__('Amharic', 'wp-store-locator')                 => 'am',
            esc_html__('Arabic', 'wp-store-locator')                  => 'ar',
            esc_html__('Armenian', 'wp-store-locator')                => 'hy',
            esc_html__('Azerbaijani', 'wp-store-locator')             => 'az',
            esc_html__('Basque', 'wp-store-locator')                  => 'eu',
            esc_html__('Belarusian', 'wp-store-locator')              => 'be',
            esc_html__('Bengali', 'wp-store-locator')                 => 'bn',
            esc_html__('Bosnian', 'wp-store-locator')                 => 'bs',
            esc_html__('Bulgarian', 'wp-store-locator')               => 'bg',
            esc_html__('Burmese', 'wp-store-locator')                 => 'my',
            esc_html__('Catalan', 'wp-store-locator')                 => 'ca',
            esc_html__('Chinese', 'wp-store-locator')                 => 'zh',
            esc_html__('Chinese (Simplified)', 'wp-store-locator')    => 'zh-CN',
            esc_html__('Chinese (Hong Kong)', 'wp-store-locator')     => 'zh-HK',
            esc_html__('Chinese (Traditional)' , 'wp-store-locator')   => 'zh-TW',
            esc_html__('Croatian', 'wp-store-locator')                => 'hr',
            esc_html__('Czech', 'wp-store-locator')                   => 'cs',
            esc_html__('Danish', 'wp-store-locator')                  => 'da',
            esc_html__('Dutch', 'wp-store-locator')                   => 'nl',
            esc_html__('English (Australian)', 'wp-store-locator')    => 'en-AU',
            esc_html__('English (Great Britain)', 'wp-store-locator') => 'en-GB',
            esc_html__('Estonian', 'wp-store-locator')                => 'et',
            esc_html__('Farsi', 'wp-store-locator')                   => 'fa',
            esc_html__('Finnish', 'wp-store-locator')                 => 'fi',
            esc_html__('Filipino', 'wp-store-locator')                => 'fil',
            esc_html__('French', 'wp-store-locator')                  => 'fr',
            esc_html__('French (Canada)', 'wp-store-locator')         => 'fr-CA',
            esc_html__('Galician', 'wp-store-locator')                => 'gl',
            esc_html__('Georgian', 'wp-store-locator')                => 'ka',
            esc_html__('German', 'wp-store-locator')                  => 'de',
            esc_html__('Greek', 'wp-store-locator')                   => 'el',
            esc_html__('Gujarati', 'wp-store-locator')                => 'gu',
            esc_html__('Hebrew', 'wp-store-locator')                  => 'iw',
            esc_html__('Hindi', 'wp-store-locator')                   => 'hi',
            esc_html__('Hungarian', 'wp-store-locator')               => 'hu',
            esc_html__('Icelandic', 'wp-store-locator')               => 'is',
            esc_html__('Indonesian', 'wp-store-locator')              => 'id',
            esc_html__('Italian', 'wp-store-locator')                 => 'it',
            esc_html__('Japanese', 'wp-store-locator')                => 'ja',
            esc_html__('Kannada', 'wp-store-locator')                 => 'kn',
            esc_html__('Kazakh', 'wp-store-locator')                  => 'kk',
            esc_html__('Khmer', 'wp-store-locator')                   => 'km',
            esc_html__('Korean', 'wp-store-locator')                  => 'ko',
            esc_html__('Kyrgyz', 'wp-store-locator')                  => 'ky',
            esc_html__('Lao', 'wp-store-locator')                     => 'lo',
            esc_html__('Latvian', 'wp-store-locator')                 => 'lv',
            esc_html__('Lithuanian', 'wp-store-locator')              => 'lt',
            esc_html__('Macedonian', 'wp-store-locator')              => 'mk',
            esc_html__('Malay', 'wp-store-locator')                   => 'ms',
            esc_html__('Malayalam', 'wp-store-locator')               => 'ml',
            esc_html__('Marathi', 'wp-store-locator')                 => 'mr',
            esc_html__('Mongolian', 'wp-store-locator')               => 'mn',
            esc_html__('Nepali', 'wp-store-locator')                  => 'ne',
            esc_html__('Norwegian', 'wp-store-locator')               => 'no',
            esc_html__('Polish', 'wp-store-locator')                  => 'pl',
            esc_html__('Portuguese', 'wp-store-locator')              => 'pt',
            esc_html__('Portuguese (Brazil)', 'wp-store-locator')     => 'pt-BR',
            esc_html__('Portuguese (Portugal)', 'wp-store-locator')   => 'pt-PT',
            esc_html__('Punjabi', 'wp-store-locator')                 => 'pa',
            esc_html__('Romanian', 'wp-store-locator')                => 'ro',
            esc_html__('Russian', 'wp-store-locator')                 => 'ru',
            esc_html__('Serbian', 'wp-store-locator')                 => 'sr',
            esc_html__('Sinhalese', 'wp-store-locator')               => 'si',
            esc_html__('Slovak', 'wp-store-locator')                  => 'sk',
            esc_html__('Slovenian', 'wp-store-locator')               => 'sl',
            esc_html__('Spanish', 'wp-store-locator')                 => 'es',
            esc_html__('Spanish (Latin America)', 'wp-store-locator') => 'es-419',
            esc_html__('Swahili', 'wp-store-locator')                 => 'sw',
            esc_html__('Swedish', 'wp-store-locator')                 => 'sv',
            esc_html__('Tamil', 'wp-store-locator')                   => 'ta',
            esc_html__('Telugu', 'wp-store-locator')                  => 'te',
            esc_html__('Thai', 'wp-store-locator')                    => 'th',
            esc_html__('Turkish', 'wp-store-locator')                 => 'tr',
            esc_html__('Ukrainian', 'wp-store-locator')               => 'uk',
            esc_html__('Urdu', 'wp-store-locator')                    => 'ur',
            esc_html__('Uzbek', 'wp-store-locator')                   => 'uz',
            esc_html__('Vietnamese', 'wp-store-locator')              => 'vi',
            esc_html__('Zulu', 'wp-store-locator')                    => 'zu',
        ],
        'openrouteservice_language' => [
            esc_html__('Select your language', 'wp-store-locator') => '',
            esc_html__('English', 'wp-store-locator')              => 'en',
            esc_html__('Chinese', 'wp-store-locator')              => 'zh',
            esc_html__('German', 'wp-store-locator')               => 'de',
            esc_html__('Portuguese', 'wp-store-locator')           => 'pt',
            esc_html__('Greek', 'wp-store-locator')                => 'gr',
            esc_html__('Russian', 'wp-store-locator')              => 'ru',
            esc_html__('Hungarian', 'wp-store-locator')            => 'hu',
            esc_html__('French', 'wp-store-locator')               => 'fr',
            esc_html__('Italian', 'wp-store-locator')              => 'it',
            esc_html__('Dutch', 'wp-store-locator')                => 'nl'
        ],
        'mapbox_language' => [
            esc_html__('Select your language', 'wp-store-locator') => '',
            esc_html__( '- Global coverage', 'wp-store-locator')   => '-',
            esc_html__('English ', 'wp-store-locator')  => 'en',
            esc_html__('German ', 'wp-store-locator')   => 'de',
            esc_html__('Spanish ', 'wp-store-locator')  => 'es',
            esc_html__('French ', 'wp-store-locator')   => 'fr',
            esc_html__('Italian ', 'wp-store-locator')  => 'it',
            esc_html__('Dutch ', 'wp-store-locator')    => 'nl',
            esc_html__('Polish ', 'wp-store-locator')   => 'pl',
            esc_html__( '- Local coverage', 'wp-store-locator')  => '-',
            esc_html__( 'Azerbaijani', 'wp-store-locator')   => 'az',
            esc_html__( 'Bengali', 'wp-store-locator')   => 'bn',
            esc_html__( 'Catalan', 'wp-store-locator')   => 'ca',
            esc_html__( 'Czech', 'wp-store-locator')   => 'cs',
            esc_html__( 'Danish', 'wp-store-locator')   => 'da',
            esc_html__( 'Modern Greek', 'wp-store-locator')   => 'el',
            esc_html__( 'Farsi', 'wp-store-locator')   => 'fa',
            esc_html__( 'Finnish', 'wp-store-locator')   => 'fi',
            esc_html__( 'Filipino', 'wp-store-locator')   => 'fil',
            esc_html__( 'Galician', 'wp-store-locator')   => 'gl',
            esc_html__( 'Georgian', 'wp-store-locator')   => 'ka',
            esc_html__( 'German', 'wp-store-locator')   => 'de',
            esc_html__( 'Greek', 'wp-store-locator')   => 'el',
            esc_html__( 'Gujarati', 'wp-store-locator')   => 'gu',
            esc_html__( 'Hebrew', 'wp-store-locator')   => 'iw',
            esc_html__( 'Hindi', 'wp-store-locator')   => 'hi',
            esc_html__( 'Hungarian', 'wp-store-locator')   => 'hu',
            esc_html__( 'Icelandic', 'wp-store-locator')   => 'is',
            esc_html__( 'Indonesian', 'wp-store-locator')   => 'id',
            esc_html__( 'Italian', 'wp-store-locator')   => 'it',
            esc_html__( 'Japanese', 'wp-store-locator')   => 'ja',
            esc_html__( 'Kannada', 'wp-store-locator')   => 'kn',
            esc_html__( 'Kazakh', 'wp-store-locator')   => 'kk',
            esc_html__( 'Khmer', 'wp-store-locator')   => 'km',
            esc_html__( 'Korean', 'wp-store-locator')   => 'ko',
            esc_html__( 'Kyrgyz', 'wp-store-locator')   => 'ky',
            esc_html__( 'Lao', 'wp-store-locator')   => 'lo',
            esc_html__( 'Latvian', 'wp-store-locator')   => 'lv',
            esc_html__( 'Lithuanian', 'wp-store-locator')   => 'lt',
            esc_html__( 'Macedonian', 'wp-store-locator')   => 'mk',
            esc_html__( 'Malay', 'wp-store-locator')   => 'ms',
            esc_html__( 'Malayalam', 'wp-store-locator')   => 'ml',
            esc_html__( 'Marathi', 'wp-store-locator')   => 'mr',
            esc_html__( 'Mongolian', 'wp-store-locator')   => 'mn',
            esc_html__( 'Nepali', 'wp-store-locator')   => 'ne',
            esc_html__( 'Norwegian', 'wp-store-locator')   => 'no',
            esc_html__( 'Polish', 'wp-store-locator')   => 'pl',
            esc_html__( 'Portuguese', 'wp-store-locator')   => 'pt',
            esc_html__( 'Portuguese (Brazil)', 'wp-store-locator')   => 'pt-BR',
            esc_html__( 'Portuguese (Portugal)', 'wp-store-locator')   => 'pt-PT',
            esc_html__( 'Punjabi', 'wp-store-locator')   => 'pa',
            esc_html__( 'Romanian', 'wp-store-locator')   => 'ro',
            esc_html__( 'Russian', 'wp-store-locator')   => 'ru',
            esc_html__( 'Serbian', 'wp-store-locator')   => 'sr',
            esc_html__( 'Sinhalese', 'wp-store-locator')   => 'si',
            esc_html__( 'Slovak', 'wp-store-locator')   => 'sk',
            esc_html__( 'Slovenian', 'wp-store-locator')   => 'sl',
            esc_html__( 'Spanish', 'wp-store-locator')   => 'es',
            esc_html__( 'Spanish (Latin America)', 'wp-store-locator')   => 'es-419',
            esc_html__( 'Swahili', 'wp-store-locator')   => 'sw',
            esc_html__( 'Swedish', 'wp-store-locator')   => 'sv',
            esc_html__( 'Tamil', 'wp-store-locator')   => 'ta',
            esc_html__( 'Telugu', 'wp-store-locator')   => 'te',
            esc_html__( 'Thai', 'wp-store-locator')   => 'th',
            esc_html__( 'Turkish', 'wp-store-locator')   => 'tr',
            esc_html__( 'Ukrainian', 'wp-store-locator')   => 'uk',
            esc_html__( 'Urdu', 'wp-store-locator')   => 'ur',
            esc_html__( 'Uzbek', 'wp-store-locator')   => 'uz',
            esc_html__( 'Vietnamese', 'wp-store-locator')   => 'vi',
            esc_html__( 'Zulu', 'wp-store-locator')   => 'zu',
        ],
        'mapbox_language' => [
            esc_html__('Select your language', 'wp-store-locator') => '',
            esc_html__( '- Global coverage', 'wp-store-locator')   => '-',
            esc_html__('English ', 'wp-store-locator')  => 'en',
            esc_html__('German ', 'wp-store-locator')   => 'de',
            esc_html__('Spanish ', 'wp-store-locator')  => 'es',
            esc_html__('French ', 'wp-store-locator')   => 'fr',
            esc_html__('Italian ', 'wp-store-locator')  => 'it',
            esc_html__('Dutch ', 'wp-store-locator')    => 'nl',
            esc_html__('Polish ', 'wp-store-locator')   => 'pl',
            esc_html__( '- Local coverage', 'wp-store-locator')  => '-',
            esc_html__( 'Azerbaijani', 'wp-store-locator')   => 'az',
            esc_html__( 'Bengali', 'wp-store-locator')   => 'bn',
            esc_html__( 'Catalan', 'wp-store-locator')   => 'ca',
            esc_html__( 'Czech', 'wp-store-locator')   => 'cs',
            esc_html__( 'Danish', 'wp-store-locator')   => 'da',
            esc_html__( 'Modern Greek', 'wp-store-locator')   => 'el',
            esc_html__( 'Persian', 'wp-store-locator')   => 'fa',
            esc_html__( 'Finnish', 'wp-store-locator')   => 'fi',
            esc_html__( 'Irish', 'wp-store-locator')   => 'ga',
            esc_html__( 'Hungarian', 'wp-store-locator')   => 'hu',
            esc_html__( 'Indonesian', 'wp-store-locator')   => 'id',
            esc_html__( 'Icelandic', 'wp-store-locator')   => 'is',
            esc_html__( 'Japanese', 'wp-store-locator')   => 'ja',
            esc_html__( 'Georgian', 'wp-store-locator')   => 'ka',
            esc_html__( 'Central Khmer', 'wp-store-locator')   => 'km',
            esc_html__( 'Korean', 'wp-store-locator')   => 'ko',
            esc_html__( 'Lithuanian', 'wp-store-locator')   => 'lt',
            esc_html__( 'Latvian', 'wp-store-locator')   => 'lv',
            esc_html__( 'Mongolian', 'wp-store-locator')   => 'mn',
            esc_html__( 'Portuguese', 'wp-store-locator')   => 'pt',
            esc_html__( 'Romanian', 'wp-store-locator')   => 'ro',
            esc_html__( 'Russian', 'wp-store-locator')   => 'ru',
            esc_html__( 'Slovak', 'wp-store-locator')   => 'sk',
            esc_html__( 'Albanian', 'wp-store-locator')   => 'sq',
            esc_html__( 'Swedish', 'wp-store-locator')   => 'sv',
            esc_html__( 'Thai', 'wp-store-locator')   => 'th',
            esc_html__( 'Tagalog', 'wp-store-locator')   => 'tl',
            esc_html__( 'Ukrainian', 'wp-store-locator')   => 'uk',
            esc_html__( 'Urdu', 'wp-store-locator')   => 'ur',
            esc_html__( 'Uzbek', 'wp-store-locator')   => 'uz',
            esc_html__( 'Vietnamese', 'wp-store-locator')   => 'vi',
            esc_html__( 'Chinese', 'wp-store-locator')   => 'zh',
            esc_html__( 'Simplified Chinese', 'wp-store-locator')   => 'zh_Hans',
            esc_html__( 'Taiwanese Mandarin', 'wp-store-locator')   => 'zh_Tw',
            esc_html__( '- Limited coverage', 'wp-store-locator')   => '-',
            esc_html__( 'Arabic', 'wp-store-locator')   => 'ar',
            esc_html__( 'Bosnian', 'wp-store-locator')   => 'bs',
            esc_html__( 'Gujarati', 'wp-store-locator')   => 'gu',
            esc_html__( 'Hebrew', 'wp-store-locator')   => 'he',
            esc_html__( 'Hindi', 'wp-store-locator')   => 'hi',
            esc_html__( 'Kazakh', 'wp-store-locator')   => 'kk',
            esc_html__( 'Lao', 'wp-store-locator')   => 'lo',
            esc_html__( 'Burmese', 'wp-store-locator')   => 'my',
            esc_html__( 'Norwegian Bokmål', 'wp-store-locator')   => 'nb',
            esc_html__( 'Russian', 'wp-store-locator')   => 'ru',
            esc_html__( 'Serbian', 'wp-store-locator')   => 'sr',
            esc_html__( 'Telugu', 'wp-store-locator')   => 'te',
            esc_html__( 'Turkmen', 'wp-store-locator')   => 'tk',
            esc_html__( 'Turkish', 'wp-store-locator')   => 'tr',
            esc_html__( 'Traditional Chinese', 'wp-store-locator')   => 'zh_Hant'
        ]
    ];

    if ( isset( $restrictions[ $type ] ) ) {
        $restrictions = $restrictions[ $type ];
    }

    return $restrictions;
}

/**
 * If only country values are provided, then we check how many we have and if
 * they are full country names or two letter iso codes. The reason for this
 * is that we can't really geocode two letter country codes to get the bounds
 * to restrict the autocomplete results.
 *
 * To fix this, assuming we have only one value is to get the full country name.
 *
 * If multiple country names are provided, then we skip the geocode / transient page
 * and won't be able to restrict the autocomplete results.
 *
 * @since 3.0.0
 * @param array $restrictions
 */
function wpsl_check_country_restriction( $restrictions ) {
    if ( count( $restrictions ) == 1 && isset( $restrictions['country'] ) ) {
        $countries = explode( ',', $restrictions['country'] );

        // See if we need to replace the two letter country code with the full country name.
        if ( count( $countries ) == 1 && strlen( $countries[0] ) == 2 && ctype_alpha( $countries[0] ) ) {
            $restrictions['country'] = wpsl_map_country_names( $countries[0] );
        } else {
            $restrictions['skip'] = true;
        }

        if ( ! $restrictions['country']) {
            $restrictions['skip'] = true;
        }
    }

    return $restrictions;
}

/**
 * Either return the two letter ISO code
 * or full country name based on the provided input
 *
 * @since  3.0.0
 * @param  string|array $name    Either the two letter country ISO code, or the full country name
 * @return string       $country Based on the provided input, it returns a single two letter ISO code, or a list of country names
 */
function wpsl_map_country_names( $name ) {
    $regions = wpsl_get_regions();

    if ( is_string( $name ) && strlen( $name ) == 2 ) { // Get the full country name based on the ISO code.
        $country = array_search( trim( $name ), $regions );
    } else {

        if ( is_array( $name ) ) {
            $list = [];

            foreach ( $name as $country ) {
                $country = trim( $country );

                if ( $country === '' ) {
                    continue;
                }

                $found = array_search( $country, $regions );

                if ( $found !== false ) {
                    $list[] = $found;
                }
            }

            if ( count( $list ) > 1 ) {
                // "Country A, Country B and Country C"
                $end     = array_pop( $list );
                $country = implode( ', ', $list ) . esc_html__( ' and ', 'wp-store-locator' ) . $end;
            } else {
                // Single ( or no ) country, so no "and" separator.
                $country = ( count( $list ) === 1 ) ? $list[0] : '';
            }
        } else {
            $country = $regions[ trim( $name ) ];
        }
    }

    return $country;
}

/**
 * Map the language code to the full language name,
 * or the otherway around.
 *
 * @since  3.0.0
 * @param  string $name     Either the two letter language ISO code, or the full language name
 * @return string $language Based on the provided input, it returns a two letter ISO code, or the full language name
 */
function wpsl_map_language_names( $name ) {
    $language  = '';
    $languages = wpsl_language_restrictions( 'all_languages' );

    if ( strlen( $name ) == 2 ) { // Get the language name based on the ISO code
        $language = array_search( trim( $name ), $languages );
    } else { // Get the language ISO code based on the full name
        $language = $languages[ trim( ucfirst( $name ) ) ];
    }

    return $language;
}