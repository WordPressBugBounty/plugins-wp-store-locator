<?php
/**
 * Handle JavaScript data for admin scripts.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Assets;

defined( 'ABSPATH' ) || exit;

use WPSL\Core\Settings\Manager as Settings;
use WPSL\Core\I18n\Translations;
    
class Resources {

    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Translations service
     *
     * @since 3.0.0
     * @var \WPSL\Core\I18n\Translations
     */
    private $i18n;
    
    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager  $settings Settings manager instance
     * @param \WPSL\Core\I18n\Translations $i18n     Translations service instance
     */
    public function __construct( Settings $settings, Translations $i18n ) {
        $this->settings = $settings;
        $this->i18n     = $i18n;
    }

    /**
     * The text messages used in wpsl-admin.js.
     *
     * @since  1.2.20
     * @return array $admin_js_l10n The texts used in the wpsl-admin.js
     */
    public function get_l10n() {
        $admin_js_l10n = [
            'preloader'         => esc_html__( 'preloader', 'wp-store-locator' ),
            'loadSection'       => esc_html__( 'Loading', 'wp-store-locator' ),
            'saveSection'       => esc_html__( 'Saving', 'wp-store-locator' ),
            'sectionUpdated'    => esc_html__( 'Section updated.', 'wp-store-locator' ),
            'sectionUpdatedInactive' => esc_html__( 'Section saved, but not active — the default code is still used.', 'wp-store-locator' ),
            'sectionRestored'   => esc_html__( 'Section restored to default.', 'wp-store-locator' ),
            'compareTitle'      => esc_html__( 'Sync settings', 'wp-store-locator' ),
            'compareInSync'     => esc_html__( 'This section is in sync with your current settings.', 'wp-store-locator' ),
            /* translators: %s: line number */
            'compareInsertAfter' => esc_html__( 'will be inserted after line %s', 'wp-store-locator' ),
            'compareModifies'   => esc_html__( 'updates existing markup', 'wp-store-locator' ),
            'compareManual'     => esc_html__( 'The default structure this feature anchors to was not found. Add the code below manually.', 'wp-store-locator' ),
            'compareRemoveManually' => esc_html__( 'requires a manual edit', 'wp-store-locator' ),
            'compareManualEditInfo' => esc_html__( 'Sync only adds missing markup, it never removes existing code, so this stays listed until you delete it from the code below yourself, or turn the option back on if you want to keep it.', 'wp-store-locator' ),
            'compareFoundOnLines' => esc_html__( 'Found on line(s):', 'wp-store-locator' ),
            /* translators: %1$s: first line of the block, %2$s: last line of the block */
            'compareFoundOnLineRange' => esc_html__( 'Found on lines %1$s to %2$s', 'wp-store-locator' ),
            /* translators: %s: number of additional lines the markup appears on, not shown */
            'compareAndMoreLines' => esc_html__( '(+%s more)', 'wp-store-locator' ),
            'compareApply'      => esc_html__( 'Apply changes', 'wp-store-locator' ),
            'compareEmpty'      => esc_html__( 'The editor is empty. Use Restore Default to load the default section code.', 'wp-store-locator' ),
            /* translators: %s: comma separated feature names */
            'compareSkipped'    => esc_html__( 'Could not merge: %s', 'wp-store-locator' ),
            'sectionsMerged'    => esc_html__( 'Merged. Review the code and save the section.', 'wp-store-locator' ),
            /* translators: %s: comma separated language names */
            'compareOtherLanguages' => esc_html__( 'The stored section for these languages needs the same changes: %s. Applying here only updates the language loaded in the editor.', 'wp-store-locator' ),
            /* translators: %s: number of other languages that are out of sync */
            'compareApplyAllLanguages' => esc_html__( 'Apply to all languages (%s)', 'wp-store-locator' ),
            /* translators: %s: comma separated language names */
            'compareLanguagesUpdated' => esc_html__( 'Also updated and saved: %s.', 'wp-store-locator' ),
            /* translators: %s: comma separated language names */
            'compareLanguagesFailed' => esc_html__( 'These languages were left unchanged because the merged code did not validate: %s. Load each one and sync it individually.', 'wp-store-locator' ),
            'compareLanguagesUnchanged' => esc_html__( 'The other languages needed no changes.', 'wp-store-locator' ),
            'noAddress'         => esc_html__( 'Cannot determine the address at this location.', 'wp-store-locator' ),
            'geocodeFail'       => esc_html__( 'Geocode was not successful for the following reason', 'wp-store-locator' ),
            'securityFail'      => esc_html__( 'Security check failed, reload the page and try again.', 'wp-store-locator' ),
            'requiredFields'    => esc_html__( 'Please fill in all the required store details.', 'wp-store-locator' ),
            'invalidEmail'      => esc_html__( 'One or more required email fields contain an invalid email address.', 'wp-store-locator' ),
            'missingGeoData'    => esc_html__( 'The map preview requires all the location details.', 'wp-store-locator' ),
            'closedDate'        => esc_html__( 'Closed', 'wp-store-locator' ),
            'NoLabel'           => esc_html__( 'No label', 'wp-store-locator' ),
            'styleError'        => esc_html__( 'Invalid map style.', 'wp-store-locator' ),
            'mapLoadFailed'     => esc_html__( 'Failed to load the map.', 'wp-store-locator' ),
            /* translators: 1: opening link tag to the Tools tab, 2: closing link tag, 3: opening link tag to the documentation, 4: closing link tag */
            'gmapsConflictAlert' => sprintf( esc_html__( 'Another plugin loaded Google Maps before the store locator could, which breaks the map. Go to %1$sTools%2$s and enable compatibility mode. %3$sRead more.%4$s', 'wp-store-locator' ), '<a href="#" class="wpsl-trigger-nav" data-item="tools">', '</a>', '<a target="_blank" href="https://wpstorelocator.co/document/another-plugin-has-already-loaded-google-maps">', '</a>' ),
            'dismissNotice'     => esc_html__( 'Dismiss this notice.', 'wp-store-locator' ),
            /* translators: 1: opening link tag for browser key documentation, 2: closing link tag, 3: line break, 4: opening link tag for browser console documentation, 5: closing link tag, 6-17: kbd tags for keyboard shortcuts, 18-19: line breaks, 20: opening link tag for troubleshooting section, 21: closing link tag */
            'browserKeyError'   => sprintf( esc_html__( 'There\'s a problem with the provided %1$sbrowser key%2$s. %3$s You will have to open the %4$sbrowser console%5$s ( %6$sctrl%7$s %8$sshift%9$s %10$sk%11$s in Firefox, or %12$sctrl%13$s %14$sshift%15$s %16$sj%17$s in Chrome ) to see the error details returned by the Google Maps API. %18$s The error itself includes a link explaining the problem in more detail. %19$s Common API errors are also covered in the %20$stroubleshooting section%21$s.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#browser-key">','</a>', '<br><br>', '<a target="_blank" href="https://codex.wordpress.org/Using_Your_Browser_to_Diagnose_JavaScript_Errors#Step_3:_Diagnosis">', '</a>', '<kbd>', '</kbd>', '<kbd>', '</kbd>','<kbd>', '</kbd>', '<kbd>', '</kbd>', '<kbd>', '</kbd>','<kbd>', '</kbd>', '<br><br>', '<br><br>', '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#api-errors">', '</a>' ),
            'browserKeySuccess' => esc_html__( 'No problems found with the browser key.', 'wp-store-locator' ),
            'mapboxKeySuccess'  => esc_html__( 'No problems found with the Mapbox API key.', 'wp-store-locator' ),
            'serverKey'         => esc_html__( 'Server key', 'wp-store-locator' ),
            /* translators: 1: opening link tag for server key documentation, 2: closing link tag */
            'serverKeyMissing'  => sprintf( esc_html__( 'No %1$sserver key%2$s found!', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#server-key">', '</a>' ),
            'browserKey'        => esc_html__( 'Browser key', 'wp-store-locator' ),
            /* translators: 1: opening link tag for browser key documentation, 2: closing link tag */
            'browserKeyMissing' => sprintf( esc_html__( 'No %1$sbrowser key%2$s found!', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#browser-key">', '</a>' ),
            /* translators: 1: opening link tag for Openrouteservice signup, 2: closing link tag */
            'openrouteserviceKeyMissing' => sprintf( esc_html__( 'Please create an Openrouteservice %1$sAPI key%2$s before trying to validate it.', 'wp-store-locator'), '<a target="_blank" href="https://openrouteservice.org/dev/#/signup">', '</a>' ),
            'restrictedZipCode' => esc_html__( 'and will only work for zip codes', 'wp-store-locator' ),
            'zipCodes' => esc_html__( 'zip codes', 'wp-store-locator' ),
            'noRestriction'     => esc_html__( 'since there are no country restrictions set in the API section, the geocode API will search for matches globally, which could lead to unexpected results.', 'wp-store-locator' ),
            /* translators: 1: opening link tag for billing documentation, 2: closing link tag, 3: opening link tag for Google Maps account, 4: closing link tag, 5: line break, 6: opening link tag for Google Billing Support, 7: closing link tag */
            'loadingError'      => sprintf( esc_html__( 'Google Maps didn\'t load correctly. Make sure you have an active %1$sbilling%2$s %3$saccount%4$s for Google Maps. %5$s If the "For development purposes only" text keeps showing after creating a billing account, then you will have to contact %6$sGoogle Billing Support%7$s.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#billing">', '</a>', '<a href="https://g.co/dev/maps-no-account">', '</a>', '<br><br>', '<a target="_blank" href="https://cloud.google.com/support/billing/">', '</a>' ),
            /* translators: 1: opening link tag for browser key documentation, 2: closing link tag, 3: line break, 4: opening link tag for browser console documentation, 5: closing link tag, 6-17: kbd tags for keyboard shortcuts, 18-19: line breaks, 20: opening link tag for troubleshooting section, 21: closing link tag */
            'loadingFailed'     => sprintf( esc_html__( 'Google Maps failed to load correctly. This is likely due to a problem with the provided %1$sbrowser key%2$s. %3$s You will have to open the %4$sbrowser console%5$s ( %6$sctrl%7$s %8$sshift%9$s %10$sk%11$s in Firefox, or %12$sctrl%13$s %14$sshift%15$s %16$sj%17$s in Chrome ) to see the error details returned by the Google Maps API. %18$s The error itself includes a link explaining the problem in more detail. %19$s Common API errors are also covered in the %20$stroubleshooting section%21$s.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#browser-key">','</a>', '<br><br>', '<a target="_blank" href="https://codex.wordpress.org/Using_Your_Browser_to_Diagnose_JavaScript_Errors#Step_3:_Diagnosis">', '</a>', '<kbd>', '</kbd>', '<kbd>', '</kbd>','<kbd>', '</kbd>', '<kbd>', '</kbd>', '<kbd>', '</kbd>','<kbd>', '</kbd>', '<br><br>', '<br><br>', '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#api-errors">', '</a>' ),
            'close'             => esc_html__( 'Close', 'wp-store-locator' ),
            'alwaysOpen'        => esc_html__( '24hrs', 'wp-store-locator' ),
            'autoLoadCacheCleared'  => esc_html__( 'Autoload cache successfully emptied.', 'wp-store-locator' ),
            'nominatimCacheCleared' => esc_html__( 'Nominatim geocode cache successfully emptied.', 'wp-store-locator' ),
            'hoursConvertNothing'   => esc_html__( 'No locations with version 1.x opening hours were found.', 'wp-store-locator' ),
            'hoursConvertLoading'   => esc_html__( 'Loading...', 'wp-store-locator' ),
            'hoursConvertUntitled'  => esc_html__( '( no name )', 'wp-store-locator' ),
            'hoursConvertStateReady' => esc_html__( 'Can be converted', 'wp-store-locator' ),
            'hoursConvertStateKeep'  => esc_html__( 'Keeps its text', 'wp-store-locator' ),
            /* translators: 1: number of locations that can be converted, 2: total number of locations with 1.x hours */
            'hoursConvertSummary'   => esc_html__( '%1$s of the %2$s locations with version 1.x opening hours can be converted. Nothing has been changed yet.', 'wp-store-locator' ),
            /* translators: %s: number of locations */
            'hoursConvertSkipped'   => esc_html__( '%s could not be read and will keep their current text.', 'wp-store-locator' ),
            'hoursConvertBefore'    => esc_html__( 'Now', 'wp-store-locator' ),
            'hoursConvertAfter'     => esc_html__( 'After converting', 'wp-store-locator' ),
            'hoursConvertReasonMissing' => esc_html__( 'This location no longer holds version 1.x opening hours.', 'wp-store-locator' ),
            'hoursConvertReasonProse' => esc_html__( 'This reads as a note rather than a set of opening times, so it is left exactly as it is.', 'wp-store-locator' ),
            'hoursConvertReasonAmbiguous' => esc_html__( 'These times have no AM / PM, so there is no telling 9 in the morning from 9 at night. Write them out in full and this one converts too.', 'wp-store-locator' ),
            'hoursConvertReasonExtra' => esc_html__( 'Besides the opening times this carries text the dropdowns cannot hold, so the whole location is left as it is. Nothing here is dropped.', 'wp-store-locator' ),
            'hoursConvertReasonTime' => esc_html__( 'This holds a time that is not on the clock, so it is left exactly as it is.', 'wp-store-locator' ),
            /* translators: 1: locations handled so far, 2: total to convert */
            'hoursConvertProgress'  => esc_html__( 'Converting %1$s / %2$s', 'wp-store-locator' ),
            /* translators: %s: number of locations */
            'hoursConvertDone'      => esc_html__( '%s locations converted. The original text is still stored on every one of them.', 'wp-store-locator' ),
            /* translators: %s: number of locations */
            'hoursConvertLeft'      => esc_html__( '%s locations kept their text because it holds more than opening times.', 'wp-store-locator' ),
            'hoursConvertSwitched'  => esc_html__( 'The store editor has been switched over to the opening hour dropdowns.', 'wp-store-locator' ),
            'hoursConvertFailed'    => esc_html__( 'The opening hours could not be converted, please try again.', 'wp-store-locator' ),
            /* translators: 1: opening link tag for Mapbox Studio, 2: closing link tag */
            'mapboxCustomStyleHelp' => sprintf( esc_html__( 'You can create your custom Mapbox style %1$shere%2$s.', 'wp-store-locator' ), '<a target="_blank" href="https://studio.mapbox.com/">', '</a>' ),
            'mapboxPrefixError'     => esc_html__( 'The Mapbox style URL needs to start with "mapbox://styles/"', 'wp-store-locator' ),
            'mapboxStyleLoadError'  => esc_html__( 'The Mapbox style failed to load, please make sure the provided style URL is correct!', 'wp-store-locator' ),
            /* translators: 1: opening link tag for API section, 2: closing link tag */
            'mapboxNoKey'           => sprintf( esc_html__( 'This feature requires a Mapbox API key, please add one in the %1$sAPI section%2$s.', 'wp-store-locator' ), '<a class="wpsl-trigger-nav" data-item="api" href="' . admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-api' ) . '"> ', '</a>' ),
            'openfreemapUrlInvalid' => esc_html__( "This URL didn't load as a map style. Make sure the URL is valid.", 'wp-store-locator' ),
            'noResults'             => $this->i18n->get_no_results_message(),
            /* translators: 1: opening link tag for partial match documentation, 2: closing link tag */
            'partialMatch'          => sprintf( esc_html__( 'The response from the Geocode API contained the %1$spartial match%2$s field, which means it failed to find the exact location. Check the address details for misspellings and/or an incomplete address.', 'wp-store-locator' ), '<a href="https://developers.google.com/maps/documentation/geocoding/requests-geocoding#results" target="_blank">', '</a>' ),
            /* translators: %s: the result type returned by the API (e.g. "postcode", "region") */
            'impreciseMatch'        => esc_html__( 'The Geocode API returned a result of type "%s", which is less precise than the address details you entered. It usually means a country restriction excluded the real address and only part of what you entered still matched inside the allowed countries. Check the address details and the selected countries.', 'wp-store-locator' ),
            /* translators: 1: the country name that was entered in the dialog, 2: the countries the results are restricted to */
            'countryMismatchRestricted' => esc_html__( 'The returned location is not in %1$s. The results are restricted to %2$s, and a country restriction makes the API drop the parts of the address it cannot match and answer with whatever does match inside the allowed countries, which is why the result can look precise while pointing at another country entirely.', 'wp-store-locator' ),
            /* translators: %s: the country name that was entered in the dialog */
            'countryMismatchGlobal' => esc_html__( 'The returned location is not in %s. Without a country restriction the API searches worldwide and answers with the best scoring match anywhere, so an address it can only match in part can be beaten by a similar one in another country.', 'wp-store-locator' ),
            'postcodeSpaceHint'     => esc_html__( 'If the address itself is correct, try the postcode without the space in it. Some geocoders only index it that way and fail to match it otherwise.', 'wp-store-locator' ),
            'osmApiError'           => esc_html__( 'Failed to get a valid response from the OpenStreetMaps API. Please try again later.', 'wp-store-locator' ),
            'fieldsDropdownOptions'  => esc_html__( 'Dropdown options', 'wp-store-locator' ),
            'fieldsDropdownPlaceholder' => esc_html__( 'List at least two options. One option per line.', 'wp-store-locator' ),
            'fieldDropdownRequirements' => esc_html__( 'Please provide at least two options.', 'wp-store-locator' ),
            'popupContent' => $this->get_popup_content(),
            'nothingToSave' => esc_html__( 'Nothing to save', 'wp-store-locator' ),
            'brokenSyntax'         => __( 'The template contains broken syntax. The code was not saved.', 'wp-store-locator' ),
            /* translators: %s: line number */
            'errorUnclosedTemplateTag' => __( 'line %s: unclosed <% tag', 'wp-store-locator' ),
            /* translators: %s: line number */
            'errorStrayTemplateClose'  => __( 'line %s: %> without matching <%', 'wp-store-locator' ),
            /* translators: 1: line number, 2: parser error description */
            'errorHtmlLine'            => __( 'line %1$s: HTML error: %2$s', 'wp-store-locator' ),
            /* translators: %s: parser error description */
            'errorHtml'                => __( 'HTML error: %s', 'wp-store-locator' ),
            'errorHtmlUnquotedAttr'    => __( 'attribute value must be wrapped in quotes', 'wp-store-locator' ),
            'errorHtmlUnclosedQuote'   => __( 'unclosed quote in attribute value', 'wp-store-locator' ),
            /* translators: 1: line number, 2: the tag name that is missing its opening bracket */
            'errorMissingOpenBracket'  => __( 'line %1$s: missing < for %2$s tag', 'wp-store-locator' ),
            /* translators: %s: compiler error description */
            'errorTemplateCompile'     => __( 'template compile error: %s', 'wp-store-locator' ),
            /* translators: %s: line number */
            'errorParenWithoutOpen'    => __( 'line %s: ) without matching (', 'wp-store-locator' ),
            /* translators: %s: line number */
            'errorBraceWithoutOpen'    => __( 'line %s: } without matching {', 'wp-store-locator' ),
            /* translators: %s: line number */
            'errorUnclosedParen'       => __( 'line %s: unclosed (', 'wp-store-locator' ),
            /* translators: %s: line number */
            'errorUnclosedBrace'       => __( 'line %s: unclosed {', 'wp-store-locator' ),
            'tryAutofix' => esc_html__( 'Try to autofix', 'wp-store-locator' ),
            'autofixApplied' => esc_html__( 'Autofix applied. Please review the changes before saving.', 'wp-store-locator' ),
            'autofixPartial' => esc_html__( 'Some issues were automatically fixed, but errors remain. Please fix them manually.', 'wp-store-locator' ),
            'autofixFailed' => esc_html__( 'Could not automatically fix the template syntax. Please fix it manually.', 'wp-store-locator' ),
            'copyCode' => esc_html__( 'Copy', 'wp-store-locator' ),
            'copyCodeSuccess' => esc_html__( 'Copied', 'wp-store-locator' ),
            'showCode' => esc_html__( 'Show code', 'wp-store-locator' ),
            'delete' => esc_html__( 'Delete', 'wp-store-locator' ),
            'thisField' => esc_html__( 'this field', 'wp-store-locator' ),
            'thisGroup' => esc_html__( 'this group', 'wp-store-locator' ),
            'requiredFieldsMissing' => esc_html__( 'Some required fields in the {groups} group(s) are missing or incomplete.', 'wp-store-locator' ),
            'noLanguageDefaultTemplate' => esc_html__('The selected language has no template available, so the default template has been loaded.', 'wp-store-locator' ),
            'customize' => esc_html__( 'Customize', 'wp-store-locator' ),
            'activate' => esc_html__( 'Activate', 'wp-store-locator' ),
            'apiKeyMissing' => wpsl_admin_api_key_missing_message(),
            // Provider-specific variants of apiKeyMissing that also link the create-a-key
            // documentation. gmaps intentionally stays on the generic string above.
            'apiKeyMissingProvider' => [
                'mapbox' => wpsl_admin_key_required_message( 'mapbox' ),
                'stadia' => wpsl_admin_key_required_message( 'stadia' ),
            ],
            'duplicateGroupNames' => esc_html__( 'Duplicate group names are not allowed. Please use unique names for each group.', 'wp-store-locator' ),
            'emptyGroupName' => esc_html__( 'Group names cannot be empty. Please provide a name for each group.', 'wp-store-locator' ),
            'duplicateFieldName' => esc_html__( 'The % field name is already used. Please use unique names for each field.', 'wp-store-locator' ),
            'protectedFieldName' => esc_html__( '"%" is not available as a field name. Please use another value.', 'wp-store-locator' ),
            'fieldNameStartsWithDigit' => esc_html__( '"%" is not a valid field name. Field names must start with a letter or underscore.', 'wp-store-locator' ),
            'applyChanges' => esc_html__( 'Apply changes', 'wp-store-locator' ),
            'deletingStores' => esc_html__( 'Deleting stores...', 'wp-store-locator' ),
            'deletingCompleted' => esc_html__( 'Deletion completed!', 'wp-store-locator' ),
            'preparingDeletion' => esc_html__( 'Preparing deletion...', 'wp-store-locator' ),
            'errorOccurredDeletion' => esc_html__( 'Error occurred during deletion', 'wp-store-locator' ),
            'errorOccured' => esc_html__( 'An error occurred. Please try again.', 'wp-store-locator' ),
            'error' => esc_html__( 'Error', 'wp-store-locator' ),
            /* translators: %s: number of stores processed */
            'successfullyProcessed' => esc_html__( 'Successfully processed %s stores (100%', 'wp-store-locator' ),
            /* translators: 1: number of stores processed, 2: total number of stores, 3: percentage */
            'progressProcessed' => esc_html__( '%1$s of %2$s stores (%3$s%)', 'wp-store-locator' ),
            'errorDuringProcessing' => esc_html__( 'An error occurred while processing your request.', 'wp-store-locator' ),
            'errorDuringDeletion' => esc_html__( 'An error occurred during deletion.', 'wp-store-locator' ),
            'dataManagement' => esc_html__( 'Data Management', 'wp-store-locator' ),
            'processingTasks' => esc_html__( 'Processing tasks...', 'wp-store-locator' ),
            'processingTasksCompleted' => esc_html__( 'All selected tasks have been completed successfully!', 'wp-store-locator' ),
            /* translators: %s: error message */
            'failedToProcess' => esc_html__( 'Failed to process: %s', 'wp-store-locator' ),
            /* translators: %s: number of locations deleted */
            'completeDeleted' => esc_html__( 'Complete: deleted %s locations', 'wp-store-locator' ),
            'preparing' => esc_html__( 'Preparing...', 'wp-store-locator' ),
            'deleteAllLocations' => esc_html__( 'Delete All Locations', 'wp-store-locator' ),
            'deleteAllCategories' => esc_html__( 'Delete All Categories', 'wp-store-locator' ),
            'deleteAllCustomMarkers' => esc_html__( 'Delete All Custom Markers', 'wp-store-locator' ),
            'deleteAllMapShapes' => esc_html__( 'Delete All Map Shapes', 'wp-store-locator' ),
            'resetSettingsToDefaults' => esc_html__( 'Reset Settings to Defaults', 'wp-store-locator' ),
            'moreDetails' => $this->i18n->get_translation( 'more_details_label', esc_html__( 'More details', 'wp-store-locator' ) ),
            'searching' => $this->i18n->get_translation( 'preloader_label', esc_html__( 'Searching...', 'wp-store-locator' ) ),
            'savingCustomization' => esc_html__( 'Saving...', 'wp-store-locator' ),
            'saveCustomization' => esc_html__( 'Save Customization', 'wp-store-locator' ),
            'saveFailed' => esc_html__( 'Failed to save settings.', 'wp-store-locator' ),
            'saveErrorOccurred' => esc_html__( 'An error occurred while saving settings.', 'wp-store-locator' ),
            /* translators: 1: opening link tag for Google Cloud Console, 2: closing link tag */
            'placesApiError' => sprintf( esc_html__( 'Google Places API Error (403): Please check your API key configuration in the %1$sGoogle Cloud Console%2$s. The Places API may not be enabled, your quota may have been exceeded, or there may be billing issues.', 'wp-store-locator' ), '<a target="_blank" href="https://console.cloud.google.com/apis/dashboard">', '</a>' ),
            'restoreDefault' => esc_html__( 'Restore default', 'wp-store-locator' ),
            'restoreDefaultFontSizes' => esc_html__( 'Restore Default Font Sizes', 'wp-store-locator' ),
            'restoreDefaultColors' => esc_html__( 'Restore Default Colors', 'wp-store-locator' ),
            'restoreDefaultDimensions' => esc_html__( 'Restore Default Dimensions', 'wp-store-locator' ),
            'contrastVeryLow'          => esc_html__( 'Very low contrast', 'wp-store-locator' ),
            'contrastLow'              => esc_html__( 'Low contrast', 'wp-store-locator' ),
            'contrastHardToRead'       => esc_html__( '— text will be hard to read on this background.', 'wp-store-locator' ),
            'contrastAutoFix'          => esc_html__( 'Auto fix', 'wp-store-locator' ),
            'contrastGradientImpossible' => sprintf(
                /* translators: 1: opening link tag to the WebAIM contrast article, 2: closing link tag, 3: double line break (do not translate). {bestRatio} is replaced by JS with the best achievable ratio (e.g. 4.2). */
                esc_html__( 'For good readability, %1$sWCAG contrast guidelines%2$s recommend a ratio of at least 4.5:1 (you have {bestRatio}:1).%3$sTo fix this, lighten the darker gradient stop.', 'wp-store-locator' ),
                '<a href="https://webaim.org/articles/contrast/#ratio" target="_blank" rel="noopener noreferrer">',
                '</a>',
                '<br><br>'
            ),
            'contrastWcagRequirement'  => sprintf(
                /* translators: 1: placeholder replaced by JS with the actual contrast ratio (e.g. 2.6), 2: opening "Read more" link tag, 3: closing link tag */
                esc_html__( 'WCAG 2.0 level AA requires a contrast ratio of at least 4.5:1, we have %1$s:1. %2$sRead more%3$s', 'wp-store-locator' ),
                '{ratio}',
                '<a href="https://webaim.org/articles/contrast/#ratio" target="_blank" rel="noopener noreferrer">',
                '</a>'
            ),
        ];

        /**
         * These texts are only shown when the user checks the API response for
         * a provided address ( tools section ), and a map region is selected.
         */
        $admin_js_l10n['resultsRestricted'] = esc_html__( 'with the current settings the results are restricted to', 'wp-store-locator' );
        $admin_js_l10n['resultsBiased']     = esc_html__( 'with the current settings the results are biased to', 'wp-store-locator' );

        return $admin_js_l10n;
    }

    /**
     * Plugin settings that are used in the wpsl-admin.js.
     *
     * @since  2.0.0
     * @param  string $active_provider Force the return of the settings for a specific provider.
     * @return array  $settings_js     The settings used in the wpsl-admin.js
     */
    public function get_settings( $active_provider = '' ) {
        global $pagenow;

        if ( empty( $active_provider ) ) {
            $active_provider = $this->settings->get( 'api', 'active_map_service' );
        }
        
        // Add provider-specific configuration based on active provider
        switch ( $active_provider ) {
            case 'gmaps':
                $provider_config = [
                    'libraries'   => wpsl_gmaps_libraries(),
                    'key'         => $this->settings->get( 'api', 'gmaps_browser_key' ),
                    'language'    => $this->settings->get( 'api', 'gmaps_language' ),
                    'hasValidKey' => ( get_option( 'wpsl_valid_gmaps_browser_key' ) == '1' ),
                    'region'      => $this->settings->get( 'api', 'gmaps_region' ),
                    'restrict'    => ( $this->settings->get( 'api', 'region_restriction_type' ) === 'restrict' && is_array( $this->settings->get( 'api', 'multiple_regions' ) ) && count( $this->settings->get( 'api', 'multiple_regions' ) ) === 1 ),
                    'style'       => $this->get_map_style( 'gmaps' ),
                    'tileLayer'   => wpsl_get_osm_tile_layer() // Needed if user switches to OSM
                ];
                break;
            case 'mapbox':
                $provider_config = [
                    'key'         => $this->settings->get( 'api', 'mapbox_key' ),
                    'hasValidKey' => get_option( 'wpsl_valid_mapbox_key' ) == '1',
                    'language'    => $this->settings->get( 'api', 'mapbox_language' ),
                    'region'      => $this->settings->get( 'api', 'multiple_regions' ),
                    'style'       => wpsl_active_mapbox_style(),
                    'tileLayer'   => wpsl_get_osm_tile_layer() // Needed if user switches to OSM
                ];
                break;
            case 'osm':
                $provider_config = [
                    'key'         => $this->settings->get( 'api', 'mapbox_key' ), // Needed for Mapbox styles on OSM
                    'hasValidKey' => get_option( 'wpsl_valid_mapbox_key' ) == '1', 
                    'language'  => $this->settings->get( 'api', 'osm_language' ),
                    'region'    => $this->settings->get( 'api', 'multiple_regions' ),
                    'tileLayer' => wpsl_get_osm_tile_layer()
                ];
                break;
            case 'stadia':
                $provider_config = [
                    'key'         => $this->settings->get( 'api', 'stadia_key' ),
                    'hasValidKey' => get_option( 'wpsl_valid_stadia_key' ) == '1', 
                    'language'  => $this->settings->get( 'api', 'stadia_language' ),
                    'region'    => $this->settings->get( 'api', 'multiple_regions' ),
                    'tileLayer' => wpsl_get_stadia_tile_layer()
                ];
                break;
            default:
                $provider_config = [];
                break;
        }

        /**
         * Always including this prevents errors when the user switches from
         * OpenStreetMaps or Mapbox back to Google Maps, and tries to validate
         * the api keys without saving the changes.
         */
        $provider_config['libraries'] = wpsl_gmaps_libraries();
        
        $js_settings = [
            'api' => array_merge(
                [ 'provider' => $active_provider ],
                $provider_config
            ),
            'ajaxurl'                     => wpsl_get_ajax_url(),
            'url'                         => WPSL_URL,
            'scriptDebug'                 => ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ),
            'version'                     => WPSL_VERSION_NUM,
            'language'                    => get_bloginfo( 'language' ),
            'hourFormat'                  => $this->settings->get( 'editor', 'hour_format' ),
            'defaultLatLng'               => $this->get_default_lat_lng(),
            'defaultZoom'                 => 6,
            'zipCode'                     => $this->settings->get( 'search', 'force_postalcode' ),
            'requiredFields'              => [ 'address', 'city' ],
            'storeMarker'                 => $this->settings->get( 'markers', 'store_marker' ),
            'storeMarkerUrl'              => $this->get_store_marker_src_override(),
            'storeMarkerRetinaUrl'        => $this->get_store_marker_retina_src(),
            'mapType'                     => $this->settings->get( 'editor', 'map_type' ),
            'mapStyle'                    => $this->get_appearance_map_style(),
            'markerUrl'                   => $this->get_marker_base_url(),
            'updateValidationStatusNonce' => wp_create_nonce( 'wpsl_update_validation_status' ),
            'locationBatchSize'           => apply_filters( 'wpsl_location_batch_size', 500 ),
            'enableStyledDropdowns'       => apply_filters( 'wpsl_enable_styled_dropdowns', true ),
            'stadiaKey'                   => $this->settings->get( 'api', 'stadia_key' ),
        ];

        /**
         * Make sure that the Geocode API testing tool
         * correctly restricts the results if required.
         */
        $gmaps_countries = ( $this->settings->get( 'api', 'region_restriction_type' ) === 'restrict' && is_array( $this->settings->get( 'api', 'multiple_regions' ) ) ) ? array_values( $this->settings->get( 'api', 'multiple_regions' ) ) : [];

        // Only a single selected country ( in hard-restrict mode ) restricts the geocode lookup ( the API supports one country ).
        if ( count( $gmaps_countries ) === 1 ) {
            $geocode_components = [];
            $geocode_components['country'] = strtoupper( $gmaps_countries[0] );

            /**
             * This prevents restricting the geocode results
             * to zip codes only when a new location is created.
             *
             * It should only be restricted on the WPSL settings page
             * for the Geocode response testing tool.
             */
            if ( $this->settings->get( 'search', 'force_postalcode' ) && $pagenow == 'edit.php' ) {
                $geocode_components['postalCode'] = '';
            }

            $js_settings['geocodeComponents'] = $geocode_components;
        }

        return apply_filters( 'wpsl_admin_js_settings', $js_settings );
    }

    /**
     * Get the map style for a specific provider
     *
     * @param  string $provider The map provider (gmaps, mapbox, etc.)
     * @return string The map style or empty string if not found
     */
    public function get_map_style( $provider ) {
        $map_style = $this->settings->get( 'appearance', 'map_style' );

        if ( is_array( $map_style ) && isset( $map_style[$provider] ) ) {
            return $map_style[$provider];
        }

        return '';
    }

    /**
     * The marker directory URL the admin maps load bundled markers from.
     *
     * @since 3.0.0
     * @return string
     */
    private function get_marker_base_url() {
        return ( defined( 'WPSL_MARKER_URI' ) ) ? WPSL_MARKER_URI : WPSL_URL . 'assets/img/frontend/markers/';
    }

    /**
     * A complete store marker src the admin JS has to use.
     *
     * The admin maps build their marker src by concatenating markerUrl with
     * storeMarker, which works for a bundled filename but 404s for a custom
     * marker stored as "custom:{id}" since it has no file on disk.
     *
     * Returning '' means the stored value is a plain filename and the JS keeps
     * its concatenation. A separate key rather than overwriting storeMarker,
     * so the filename the picker and the save handler speak in stays a
     * filename.
     *
     * @since  3.0.0
     * @return string The finished src, or '' to leave the JS concatenation alone.
     */
    private function get_store_marker_src_override() {
        $store_marker = $this->settings->get( 'markers', 'store_marker' );
        $custom_id    = wpsl_custom_marker_id( $store_marker );

        if ( ! $custom_id ) {
            $src = wpsl_marker_src( $store_marker );

            return ( $src === $this->get_marker_base_url() . $store_marker ) ? '' : $src;
        }

        $data_uri = wpsl_custom_marker_data_uri( $custom_id );

        if ( $data_uri ) {
            return $data_uri;
        }

        /**
         * The selected marker was deleted. Fall back to the shipped default
         * rather than letting the stale "custom:{id}" reach the concatenation.
         */
        return $this->get_marker_base_url() . $this->settings->get_default( 'markers', 'store_marker' );
    }

    /**
     * The retina (2x) store marker src for high-DPI admin screens.
     *
     * @since  3.0.0
     * @return string The finished src, or '' when no marker is configured.
     */
    private function get_store_marker_retina_src() {
        $store_marker = $this->settings->get( 'markers', 'store_marker' );

        /**
         * The selected marker was deleted. Fall back to the shipped default,
         * matching get_store_marker_src_override() rather than leaving the
         * retina slot pointing at a marker that no longer draws.
         */
        if ( wpsl_custom_marker_id( $store_marker ) && ! wpsl_marker_src( $store_marker ) ) {
            $store_marker = $this->settings->get_default( 'markers', 'store_marker' );
        }

        return wpsl_marker_retina_src( $store_marker );
    }

    /**
     * Get the appearance map style settings for use in the admin JS.
     *
     * This allows the editor map preview to use the same map style
     * that the user configured on the appearance page.
     *
     * @since  3.0.0
     * @return array The appearance map style data for JS consumption
     */
    public function get_appearance_map_style() {
        $map_style = $this->settings->get( 'appearance', 'map_style' );
        $result    = [];

        if ( isset( $map_style['gmaps'] ) ) {
            $result['gmaps'] = [
                'selected'    => isset( $map_style['gmaps']['selected'] ) ? $map_style['gmaps']['selected'] : 'cloud_based',
                'cloud_based' => isset( $map_style['gmaps']['cloud_based'] ) ? $map_style['gmaps']['cloud_based'] : '',
                'json'        => wpsl_get_service( 'map_settings' )->get_map_style( 'gmaps' ),
            ];
        }

        return $result;
    }

    /**
     * Get the coordinates that are used to
     * show the map on the settings page.
     * 
     * If no start coordinates exists we 
     * set the default to Googleplex.
     *
     * @since 2.2.5
     * @return string $startLatLng The start coordinates
     */
    public function get_default_lat_lng() {
        $startLatLng = $this->settings->get( 'map', 'start_latlng' );

        if ( ! $startLatLng ) {
            $startLatLng = '37.4220, -122.0840';
        }

        return $startLatLng;
    }

    /**
     * Get the popup content for marker preview.
     *
     * @since  3.0.0
     * @return string The popup HTML content
     */
    private function get_popup_content() {
        $appearance = wpsl_get_service( 'appearance' );
        $popup_data = $appearance->location_examples( 'popup' );
        
        return $popup_data['html'];
    }

    /**
     * Get SVG icons for JavaScript.
     *
     * @since  3.0.0
     * @return array SVG icon data
     */
    public function get_svg_icons() {
        $icons = [
            'location' => wpsl_get_svg_icon( 'location' ),
            'phone'    => wpsl_get_svg_icon( 'phone' ),
            'email'    => wpsl_get_svg_icon( 'email' ),
        ];

        return $icons;
    }
}