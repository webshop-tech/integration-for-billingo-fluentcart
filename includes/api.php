<?php
/**
 * API functions for communicating with Számlázz.hu
 */

namespace SzamlazzHuFluentCart;

// Exit if accessed directly
if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Build XML for invoice generation
 * 
 * @param array $params Invoice parameters
 * @return string XML string
 */
function build_invoice_xml($params) {
    $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xmlszamla xmlns="http://www.szamlazz.hu/xmlszamla" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.szamlazz.hu/xmlszamla http://www.szamlazz.hu/szamla/docs/xsds/agent/xmlszamla.xsd"></xmlszamla>');
    
    // Settings
    $beallitasok = $xml->addChild('beallitasok');
    $beallitasok->addChild('szamlaagentkulcs', $params['api_key']);
    $beallitasok->addChild('eszamla', $params['invoice_type'] == 2 ? 'true' : 'false');
    $beallitasok->addChild('szamlaLetoltes', $params['download_pdf'] ? 'true' : 'false');
    $beallitasok->addChild('valaszVerzio', '2'); // XML response
    
    // Header
    $fejlec = $xml->addChild('fejlec');
    $fejlec->addChild('keltDatum', $params['header']['issue_date']);
    $fejlec->addChild('teljesitesDatum', $params['header']['fulfillment_date']);
    $fejlec->addChild('fizetesiHataridoDatum', $params['header']['due_date']);
    $fejlec->addChild('fizmod', $params['header']['payment_method']);
    $fejlec->addChild('penznem', $params['header']['currency']);
    $fejlec->addChild('szamlaNyelve', $params['header']['language']);
    $fejlec->addChild('megjegyzes', $params['header']['comment'] ?? '');
    $fejlec->addChild('rendelesSzam', $params['header']['order_number'] ?? '');
    $fejlec->addChild('dijbekeroSzamlaszam', $params['header']['proforma_number'] ?? '');
    $fejlec->addChild('elolegszamla', $params['header']['prepayment_invoice'] ?? 'false');
    $fejlec->addChild('vegszamla', $params['header']['final_invoice'] ?? 'false');
    $fejlec->addChild('helyesbitoszamla', $params['header']['corrective_invoice'] ?? 'false');
    $fejlec->addChild('helyesbitettSzamlaszam', $params['header']['corrective_invoice_number'] ?? '');
    $fejlec->addChild('dijbekero', $params['header']['proforma'] ?? 'false');
    $fejlec->addChild('szamlaszamElotag', $params['header']['invoice_prefix'] ?? '');
    $fejlec->addChild('fizetve', $params['header']['paid'] ?? 'false');
    
    // Seller (optional, uses account defaults)
    if (!empty($params['seller'])) {
        $elado = $xml->addChild('elado');
        if (!empty($params['seller']['bank'])) {
            $elado->addChild('bank', $params['seller']['bank']);
        }
        if (!empty($params['seller']['bank_account'])) {
            $elado->addChild('bankszamlaszam', $params['seller']['bank_account']);
        }
        if (!empty($params['seller']['email_reply_to'])) {
            $elado->addChild('emailReplyto', $params['seller']['email_reply_to']);
        }
        if (!empty($params['seller']['email_subject'])) {
            $elado->addChild('emailTargy', $params['seller']['email_subject']);
        }
        if (!empty($params['seller']['email_content'])) {
            $elado->addChild('emailSzoveg', $params['seller']['email_content']);
        }
    }
    
    // Buyer
    $vevo = $xml->addChild('vevo');
    $vevo->addChild('nev', $params['buyer']['name']);
    $vevo->addChild('orszag', $params['buyer']['country'] ?? '');
    $vevo->addChild('irsz', $params['buyer']['postcode']);
    $vevo->addChild('telepules', $params['buyer']['city']);
    $vevo->addChild('cim', $params['buyer']['address']);
    
    if (!empty($params['buyer']['email'])) {
        $vevo->addChild('email', $params['buyer']['email']);
    }
    if (!empty($params['buyer']['send_email'])) {
        $vevo->addChild('sendEmail', $params['buyer']['send_email'] ? 'true' : 'false');
    }
    if (!empty($params['buyer']['tax_number'])) {
        $vevo->addChild('adoszam', $params['buyer']['tax_number']);
    }
    if (!empty($params['buyer']['tax_number_eu'])) {
        $vevo->addChild('adoszamEU', $params['buyer']['tax_number_eu']);
    }
    if (!empty($params['buyer']['phone'])) {
        $vevo->addChild('telefonszam', $params['buyer']['phone']);
    }
    if (!empty($params['buyer']['comment'])) {
        $vevo->addChild('megjegyzes', $params['buyer']['comment']);
    }
    
    // Items
    $tetelek = $xml->addChild('tetelek');
    foreach ($params['items'] as $item) {
        $tetel = $tetelek->addChild('tetel');
        $tetel->addChild('megnevezes', $item['name']);
        $tetel->addChild('mennyiseg', $item['quantity']);
        $tetel->addChild('mennyisegiEgyseg', $item['unit']);
        $tetel->addChild('nettoEgysegar', $item['unit_price']);
        $tetel->addChild('afakulcs', $item['vat_rate']);
        $tetel->addChild('netto', $item['net_price']);
        $tetel->addChild('afa', $item['vat_amount']);
        $tetel->addChild('brutto', $item['gross_amount']);
        
        if (!empty($item['comment'])) {
            $tetel->addChild('megjegyzes', $item['comment']);
        }
    }
    
    return $xml->asXML();
}

/**
 * Generate invoice via Számlázz.hu API
 * 
 * @param int $order_id The order ID for logging
 * @param string $api_key Számlázz.hu API key
 * @param array $params Invoice parameters
 * @return array|\WP_Error Array with invoice data on success, or WP_Error on failure
 */
function generate_invoice_api($order_id, $api_key, $params) {
    // Add API key to params
    $params['api_key'] = $api_key;
    
    // Set default values
    $params['download_pdf'] = $params['download_pdf'] ?? true;
    $params['invoice_type'] = $params['invoice_type'] ?? 1; // Paper invoice
    
    // Build XML request
    $xml_string = build_invoice_xml($params);
    
    // Send request to Számlázz.hu API
    $response = \wp_remote_post('https://www.szamlazz.hu/szamla/', array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'text/xml; charset=UTF-8',
        ),
        'body' => $xml_string,
    ));
    
    // Check for HTTP errors
    if (\is_wp_error($response)) {
        return $response;
    }
    
    $response_code = \wp_remote_retrieve_response_code($response);
    $response_body = \wp_remote_retrieve_body($response);
    $response_headers = \wp_remote_retrieve_headers($response);
    
    // Check response code
    if ($response_code !== 200) {
        return create_error($order_id, 'api_error', 'API returned error code', $response_code, $response_headers, $response_body);
    }
    
    // Parse response based on content type
    $content_type = $response_headers['content-type'] ?? '';
    
    if (strpos($content_type, 'application/pdf') !== false) {
        // Response is PDF (text mode response)
        return create_error($order_id, 'api_error', 'Unexpected PDF response. XML response expected.');
    } elseif (strpos($content_type, 'text/xml') !== false || strpos($content_type, 'application/xml') !== false) {
        // Response is XML
        try {
            $xml = new \SimpleXMLElement($response_body);
            
            // Check for errors
            $success = (string)$xml->sikeres;
            $error_code = (string)$xml->hibakod;
            $error_message = (string)$xml->hibauzenet;
            
            if ($success === 'false' || !empty($error_code)) {
                return create_error($order_id, 'api_error', sprintf('Invoice generation failed [%s]', $error_code), $error_message);
            }
            
            // Extract invoice data
            $result = array(
                'success' => true,
                'invoice_number' => (string)$xml->szamlaszam,
                'invoice_net' => (string)$xml->szamlanetto,
                'invoice_gross' => (string)$xml->szamlabrutto,
                'buyer_account_id' => (string)$xml->vevoifiokurl,
                'pdf_data' => null,
            );
            
            // Extract PDF if present
            if (isset($xml->pdf) && !empty($xml->pdf)) {
                $result['pdf_data'] = base64_decode((string)$xml->pdf);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            return create_error($order_id, 'parse_error', 'Failed to parse XML response', $e->getMessage());
        }
    } else {
        // Unknown response type, try to parse as text error
        return create_error($order_id, 'api_error', 'Unknown response type', substr($response_body, 0, 200));
    }
}

/**
 * Build XML for taxpayer query
 * 
 * @param string $api_key Számlázz.hu API key
 * @param string $tax_number Tax number (törzsszám - first 8 digits)
 * @return string XML string
 */
function build_taxpayer_xml($api_key, $tax_number) {
    $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xmltaxpayer xmlns="http://www.szamlazz.hu/xmltaxpayer" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.szamlazz.hu/xmltaxpayer http://www.szamlazz.hu/szamla/docs/xsds/taxpayer/xmltaxpayer.xsd"></xmltaxpayer>');
    
    $beallitasok = $xml->addChild('beallitasok');
    $beallitasok->addChild('szamlaagentkulcs', $api_key);
    
    $xml->addChild('torzsszam', substr($tax_number, 0, 8));
    
    return $xml->asXML();
}

/**
 * Query taxpayer data from NAV via Számlázz.hu API
 * 
 * @param int $order_id The order ID for logging
 * @param string $api_key Számlázz.hu API key
 * @param string $tax_number Tax number (törzsszám - first 8 digits)
 * @return array|\WP_Error Array with taxpayer data on success, or WP_Error on failure
 */
function get_taxpayer_api($order_id, $api_key, $tax_number) {
    // Build XML request
    $xml_string = build_taxpayer_xml($api_key, $tax_number);
    
    // Send request to Számlázz.hu API
    $response = \wp_remote_post('https://www.szamlazz.hu/szamla/', array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'text/xml; charset=UTF-8',
        ),
        'body' => $xml_string,
    ));
    
    // Check for HTTP errors
    if (\is_wp_error($response)) {
        return $response;
    }
    
    $response_code = \wp_remote_retrieve_response_code($response);
    $response_body = \wp_remote_retrieve_body($response);
    
    // Check response code
    if ($response_code !== 200) {
        return create_error($order_id, 'api_error', 'Taxpayer API returned error code', $response_code);
    }
    
    // Parse XML response
    try {
        $xml = new \SimpleXMLElement($response_body);
        
        // Register namespaces
        $xml->registerXPathNamespace('ns2', 'http://schemas.nav.gov.hu/OSA/3.0/api');
        $xml->registerXPathNamespace('ns3', 'http://schemas.nav.gov.hu/OSA/3.0/base');
        
        // Check if taxpayer is valid
        $taxpayerValidity = $xml->xpath('//ns2:taxpayerValidity');
        if (empty($taxpayerValidity) || "true" !== (string)$taxpayerValidity[0]) {
            return create_error($order_id, 'invalid_taxpayer', 'Taxpayer is not valid');
        }
        
        $data = array(
            'valid' => true,
            'xml' => $response_body,
        );
        
        // Extract taxpayer name
        $taxpayer_short_name = $xml->xpath('//ns2:taxpayerShortName');
        $taxpayer_name = $xml->xpath('//ns2:taxpayerName');
        
        if (!empty($taxpayer_short_name)) {
            $data['name'] = (string)$taxpayer_short_name[0];
        } elseif (!empty($taxpayer_name)) {
            $data['name'] = (string)$taxpayer_name[0];
        }
        
        // Extract VAT ID components
        $taxpayer_id = $xml->xpath('//ns3:taxpayerId');
        $vat_code = $xml->xpath('//ns3:vatCode');
        $county_code = $xml->xpath('//ns3:countyCode');
        
        if (!empty($taxpayer_id) && !empty($vat_code) && !empty($county_code)) {
            $data['vat_id'] = sprintf(
                '%s-%s-%s',
                (string)$taxpayer_id[0],
                (string)$vat_code[0],
                (string)$county_code[0]
            );
        }
        
        // Extract address
        $postal_code = $xml->xpath('//ns3:postalCode');
        $city = $xml->xpath('//ns3:city');
        $street_name = $xml->xpath('//ns3:streetName');
        $public_place = $xml->xpath('//ns3:publicPlaceCategory');
        $number = $xml->xpath('//ns3:number');
        $door = $xml->xpath('//ns3:door');
        
        if (!empty($postal_code)) {
            $data['postcode'] = (string)$postal_code[0];
        }
        
        if (!empty($city)) {
            $data['city'] = (string)$city[0];
        }
        
        if (!empty($street_name)) {
            $address_parts = [(string)$street_name[0]];
            
            if (!empty($public_place)) {
                $address_parts[] = (string)$public_place[0];
            }
            
            if (!empty($number)) {
                $address_parts[] = (string)$number[0];
            }
            
            if (!empty($door)) {
                $address_parts[] = (string)$door[0];
            }
            
            $data['address'] = implode(' ', $address_parts);
        }
        
        return $data;
        
    } catch (\Exception $e) {
        return create_error($order_id, 'parse_error', 'Failed to parse taxpayer XML', $e->getMessage());
    }
}

/**
 * Fetch invoice PDF from Számlázz.hu API using WordPress HTTP API
 * 
 * @param string $api_key Számlázz.hu API key
 * @param string $invoice_number Invoice number
 * @return array|\WP_Error Array with 'success' boolean and 'pdf_data' on success, or WP_Error on failure
 */
function fetch_invoice_pdf($api_key, $invoice_number) {
    // Build XML request for invoice PDF
    $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xmlszamlapdf xmlns="http://www.szamlazz.hu/xmlszamlapdf" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.szamlazz.hu/xmlszamlapdf http://www.szamlazz.hu/szamla/docs/xsds/agentpdf/xmlszamlapdf.xsd"></xmlszamlapdf>');
    
    // Add authentication
    $beallitasok = $xml->addChild('beallitasok');
    $beallitasok->addChild('szamlaagentkulcs', $api_key);
    $beallitasok->addChild('szamlaLetoltes', 'true');
    
    // Add invoice number
    $fejlec = $xml->addChild('fejlec');
    $fejlec->addChild('szamlaszam', $invoice_number);
    
    $xml_string = $xml->asXML();
    
    // Send request to Számlázz.hu API using WordPress HTTP API
    $response = \wp_remote_post('https://www.szamlazz.hu/szamla/', array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'text/xml; charset=UTF-8',
        ),
        'body' => $xml_string,
    ));
    
    // Check for HTTP errors
    if (\is_wp_error($response)) {
        return $response;
    }
    
    $response_code = \wp_remote_retrieve_response_code($response);
    $response_body = \wp_remote_retrieve_body($response);
    $response_headers = \wp_remote_retrieve_headers($response);
    
    // Check response code
    if ($response_code !== 200) {
        return new \WP_Error('api_error', 'API returned error code: ' . $response_code);
    }
    
    // Check if response is PDF or error message
    if (isset($response_headers['content-type']) && strpos($response_headers['content-type'], 'application/pdf') !== false) {
        // Success - got PDF
        return array(
            'success' => true,
            'pdf_data' => $response_body,
            'filename' => 'invoice_' . $invoice_number . '.pdf'
        );
    } else {
        // Error response (usually XML)
        return new \WP_Error('api_error', 'Failed to retrieve PDF: ' . substr($response_body, 0, 200));
    }
}
