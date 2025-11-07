<?php

namespace BillingoFluentCart;

if (!\defined('ABSPATH')) {
    exit;
}

function build_multipart_body($xml_string, $field_name) {
    $boundary = wp_generate_password(24, false);
    $body = '';
    
    $body .= "--{$boundary}\r\n";
    $body .= 'Content-Disposition: form-data; name="' . $field_name . '"; filename="request.xml"' . "\r\n";
    $body .= "Content-Type: application/xml\r\n\r\n";
    $body .= $xml_string . "\r\n";
    $body .= "--{$boundary}--\r\n";
    
    return array(
        'boundary' => $boundary,
        'body' => $body,
    );
}

function build_invoice_xml($params) {
    $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xmlszamla xmlns="http://www.szamlazz.hu/xmlszamla" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.szamlazz.hu/xmlszamla http://www.szamlazz.hu/szamla/docs/xsds/agent/xmlszamla.xsd"></xmlszamla>');
    
    $beallitasok = $xml->addChild('beallitasok');
    $beallitasok->addChild('szamlaagentkulcs', $params['api_key']);
    $beallitasok->addChild('eszamla', $params['invoice_type'] == 2 ? 'true' : 'false');
    $beallitasok->addChild('szamlaLetoltes', $params['download_pdf'] ? 'true' : 'false');
    $beallitasok->addChild('valaszVerzio', '2'); // XML response
    
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
    $fejlec->addChild('fizetve', $params['header']['paid'] ?? 'false');
    
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
    
    $tetelek = $xml->addChild('tetelek');
    foreach ($params['items'] as $item) {
        $tetel = $tetelek->addChild('tetel');
        $tetel->addChild('megnevezes', $item['name']);
        $tetel->addChild('mennyiseg', $item['quantity']);
        $tetel->addChild('mennyisegiEgyseg', $item['unit']);
        $tetel->addChild('nettoEgysegar', $item['unit_price']);
        $tetel->addChild('afakulcs', $item['vat_rate']);
        $tetel->addChild('nettoErtek', $item['net_price']);
        $tetel->addChild('afaErtek', $item['vat_amount']);
        $tetel->addChild('bruttoErtek', $item['gross_amount']);
        
        if (!empty($item['comment'])) {
            $tetel->addChild('megjegyzes', $item['comment']);
        }
    }
    
    return $xml->asXML();
}

function generate_invoice_api($order_id, $api_key, $params) {
    $params['api_key'] = $api_key;
    
    $params['download_pdf'] = $params['download_pdf'] ?? true;
    $params['invoice_type'] = $params['invoice_type'] ?? 1; // Paper invoice
    
    $xml_string = build_invoice_xml($params);
    
    $multipart = build_multipart_body($xml_string, 'action-xmlagentxmlfile');
    
    $response = \wp_remote_post('https://www.szamlazz.hu/szamla/', array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'multipart/form-data; boundary=' . $multipart['boundary'],
        ),
        'body' => $multipart['body'],
    ));
    
    if (\is_wp_error($response)) {
        return $response;
    }
    
    $response_code = \wp_remote_retrieve_response_code($response);
    $response_body = \wp_remote_retrieve_body($response);
    $response_headers = \wp_remote_retrieve_headers($response);
    
    if ($response_code !== 200) {
        return create_error($order_id, 'api_error', 'API returned error code', $response_code, $response_body);
    }
    
    try {
        $xml = new \SimpleXMLElement($response_body);
        
        $success = (string)$xml->sikeres;
        $error_code = (string)$xml->hibakod;
        $error_message = (string)$xml->hibauzenet;
        
        if ($success === 'false' || !empty($error_code)) {
            return create_error($order_id, 'api_error', sprintf('Invoice generation failed [%s]', $error_code), $error_message);
        }
        
        $result = array(
            'success' => true,
            'invoice_number' => (string)$xml->szamlaszam,
            'invoice_net' => (string)$xml->szamlanetto,
            'invoice_gross' => (string)$xml->szamlabrutto,
            'buyer_account_id' => (string)$xml->vevoifiokurl,
            'pdf_data' => null,
        );
        
        if (isset($xml->pdf) && !empty($xml->pdf)) {
            $result['pdf_data'] = base64_decode((string)$xml->pdf);
        }
        
        return $result;
        
    } catch (\Exception $e) {
        return create_error($order_id, 'parse_error', 'Failed to parse XML response', $e->getMessage());
    }
}

function build_taxpayer_xml($api_key, $tax_number) {
    $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xmltaxpayer xmlns="http://www.szamlazz.hu/xmltaxpayer" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.szamlazz.hu/xmltaxpayer http://www.szamlazz.hu/docs/xsds/agent/xmltaxpayer.xsd"></xmltaxpayer>');
    
    $beallitasok = $xml->addChild('beallitasok');
    $beallitasok->addChild('szamlaagentkulcs', $api_key);
    
    $xml->addChild('torzsszam', substr($tax_number, 0, 8));
    
    return $xml->asXML();
}

function get_taxpayer_api($order_id, $api_key, $tax_number) {
    $xml_string = build_taxpayer_xml($api_key, $tax_number);
    
    $multipart = build_multipart_body($xml_string, 'action-szamla_agent_taxpayer');
    
    $response = \wp_remote_post('https://www.szamlazz.hu/szamla/', array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'multipart/form-data; boundary=' . $multipart['boundary'],
        ),
        'body' => $multipart['body'],
    ));
    
    if (\is_wp_error($response)) {
        return $response;
    }
    
    $response_code = \wp_remote_retrieve_response_code($response);
    $response_body = \wp_remote_retrieve_body($response);
    
    if ($response_code !== 200) {
        return create_error($order_id, 'api_error', 'Taxpayer API returned error code', $response_code);
    }
    
    try {
        $xml = new \SimpleXMLElement($response_body);
        
        $xml->registerXPathNamespace('ns2', 'http://schemas.nav.gov.hu/OSA/3.0/api');
        $xml->registerXPathNamespace('ns3', 'http://schemas.nav.gov.hu/OSA/3.0/base');
        
        $taxpayerValidity = $xml->xpath('//ns2:taxpayerValidity');
        if (empty($taxpayerValidity) || "true" !== (string)$taxpayerValidity[0]) {
            return create_error($order_id, 'invalid_taxpayer', 'Taxpayer is not valid');
        }
        
        $data = array(
            'valid' => true,
            'xml' => $response_body,
        );
        
        $taxpayer_short_name = $xml->xpath('//ns2:taxpayerShortName');
        $taxpayer_name = $xml->xpath('//ns2:taxpayerName');
        
        if (!empty($taxpayer_short_name)) {
            $data['name'] = (string)$taxpayer_short_name[0];
        } elseif (!empty($taxpayer_name)) {
            $data['name'] = (string)$taxpayer_name[0];
        }
        
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

function fetch_invoice_pdf($order_id, $api_key, $invoice_number) {
    $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xmlszamlapdf xmlns="http://www.szamlazz.hu/xmlszamlapdf" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.szamlazz.hu/xmlszamlapdf https://www.szamlazz.hu/szamla/docs/xsds/agentpdf/xmlszamlapdf.xsd"></xmlszamlapdf>');
    
    $xml->addChild('szamlaagentkulcs', $api_key);
    $xml->addChild('szamlaszam', $invoice_number);
    $xml->addChild('valaszVerzio', '2'); // XML response version
    
    $xml_string = $xml->asXML();
    
    $multipart = build_multipart_body($xml_string, 'action-szamla_agent_pdf');
    
    $response = \wp_remote_post('https://www.szamlazz.hu/szamla/', array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'multipart/form-data; boundary=' . $multipart['boundary'],
        ),
        'body' => $multipart['body'],
    ));
    
    if (\is_wp_error($response)) {
        write_error_to_log($order_id, $response);
        return $response;
    }
    
    $response_code = \wp_remote_retrieve_response_code($response);
    $response_body = \wp_remote_retrieve_body($response);
    $response_headers = \wp_remote_retrieve_headers($response);
    
    if ($response_code !== 200) {
        return create_error($order_id, 'api_error', 'API returned error code', $response_code);
    }
    
    try {
        $xml = new \SimpleXMLElement($response_body);
        
        $success = (string)$xml->sikeres;
        $error_code = (string)$xml->hibakod;
        $error_message = (string)$xml->hibauzenet;
        
        if ($success === 'false' || !empty($error_code)) {
            return create_error($order_id, 'api_error', sprintf('PDF fetch failed [%s]', $error_code), $error_message);
        }
        
        if (isset($xml->pdf) && !empty($xml->pdf)) {
            return array(
                'success' => true,
                'pdf_data' => base64_decode((string)$xml->pdf),
                'filename' => 'invoice_' . $invoice_number . '.pdf',
                'invoice_number' => (string)$xml->szamlaszam,
                'invoice_net' => (string)$xml->szamlanetto,
                'invoice_gross' => (string)$xml->szamlabrutto,
            );
        } else {
            return create_error($order_id, 'api_error', 'PDF data not found in response');
        }
        
    } catch (\Exception $e) {
        return create_error($order_id, 'parse_error', 'Failed to parse PDF response XML', $e->getMessage());
    }
}
