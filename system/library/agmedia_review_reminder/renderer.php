<?php

class AgmediaReviewReminderRenderer {
	public function render(array $order, array $products, array $options) {
		$locale = isset($options['locale']) ? strtolower((string)$options['locale']) : 'hr-hr';
		$isEnglish = strpos($locale, 'en') === 0;
		$storeName = isset($options['store_name']) && $options['store_name'] !== '' ? $options['store_name'] : $order['store_name'];
		$unsubscribeUrl = isset($options['unsubscribe_url']) ? $options['unsubscribe_url'] : '';
		$subjectTemplate = isset($options['subject']) ? trim((string)$options['subject']) : '';
		$bodyTemplate = isset($options['body']) ? trim((string)$options['body']) : '';

		if ($subjectTemplate === '') {
			$subjectTemplate = $isEnglish
				? 'How did you like your order from {store_name}?'
				: 'Kako vam se svidjela narudžba iz {store_name}?';
		}

		if ($bodyTemplate === '') {
			$bodyTemplate = $isEnglish
				? '<p>Hello {firstname},</p><p>We hope you are enjoying your order <strong>#{order_id}</strong>. Your experience can help other customers choose the right product.</p><p>Please share a short review:</p>{products}<p>Thank you for your time!</p>'
				: '<p>Pozdrav {firstname},</p><p>Nadamo se da uživate u narudžbi <strong>#{order_id}</strong>. Vaše iskustvo može pomoći drugim kupcima pri odabiru.</p><p>Podijelite kratku recenziju:</p>{products}<p>Hvala vam na vremenu!</p>';
		}

		$productHtml = $this->renderProducts($products, $isEnglish);
		$customerName = trim($order['firstname'] . ' ' . $order['lastname']);
		$orderTimestamp = strtotime($order['date_added']);
		$orderDate = $orderTimestamp ? ($isEnglish ? date('Y-m-d', $orderTimestamp) : date('d.m.Y.', $orderTimestamp)) : '';
		$tokens = array(
			'{firstname}' => $this->escape($order['firstname']),
			'{lastname}' => $this->escape($order['lastname']),
			'{customer_name}' => $this->escape($customerName),
			'{store_name}' => $this->escape($storeName),
			'{order_id}' => (string)(int)$order['order_id'],
			'{order_date}' => $this->escape($orderDate),
			'{products}' => $productHtml,
			'{unsubscribe_url}' => $this->escape($unsubscribeUrl)
		);

		$body = strtr($bodyTemplate, $tokens);

		if (strpos($bodyTemplate, '{products}') === false) {
			$body .= $productHtml;
		}

		$subjectTokens = $tokens;
		$subjectTokens['{products}'] = '';
		$subjectTokens['{unsubscribe_url}'] = '';
		$subject = html_entity_decode(strip_tags(strtr($subjectTemplate, $subjectTokens)), ENT_QUOTES, 'UTF-8');
		$subject = trim(preg_replace('/[\r\n]+/', ' ', $subject));

		$unsubscribeLabel = $isEnglish
			? 'Do not send me any more review reminders'
			: 'Ne želim više primati podsjetnike za recenziju';
		$automaticLabel = $isEnglish
			? 'This is an automatic message about your purchase.'
			: 'Ovo je automatska poruka vezana uz vašu kupnju.';

		$footer = '<p style="margin:0 0 8px;color:#777;font-size:12px;line-height:18px">' . $this->escape($automaticLabel) . '</p>';

		if ($unsubscribeUrl !== '' && strpos($bodyTemplate, '{unsubscribe_url}') === false) {
			$footer .= '<p style="margin:0;color:#777;font-size:12px;line-height:18px"><a href="' . $this->escape($unsubscribeUrl) . '" style="color:#666;text-decoration:underline">' . $this->escape($unsubscribeLabel) . '</a></p>';
		}

		$html = '<!doctype html><html lang="' . ($isEnglish ? 'en' : 'hr') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $this->escape($subject) . '</title></head>' .
			'<body style="margin:0;padding:0;background:#f4f4f4;color:#282828;font-family:Arial,Helvetica,sans-serif">' .
			'<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . $this->escape($subject) . '</div>' .
			'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f4f4f4"><tr><td align="center" style="padding:24px 12px">' .
			'<table role="presentation" width="620" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:620px;background:#fff;border-radius:8px;overflow:hidden">' .
			'<tr><td style="padding:26px 30px;background:#1f2937;color:#fff;font-size:22px;font-weight:bold">' . $this->escape($storeName) . '</td></tr>' .
			'<tr><td style="padding:30px;font-size:16px;line-height:25px">' . $body . '</td></tr>' .
			'<tr><td style="padding:20px 30px;border-top:1px solid #eee">' . $footer . '</td></tr>' .
			'</table></td></tr></table></body></html>';

		$text = $this->htmlToText($body) . "\n\n" . $automaticLabel;

		if ($unsubscribeUrl !== '' && strpos($bodyTemplate, '{unsubscribe_url}') === false) {
			$text .= "\n" . $unsubscribeLabel . ': ' . $unsubscribeUrl;
		}

		return array(
			'subject' => $subject,
			'html' => $html,
			'text' => trim($text)
		);
	}

	private function renderProducts(array $products, $isEnglish) {
		$html = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:18px 0">';
		$button = $isEnglish ? 'Review product' : 'Ocijeni proizvod';

		foreach ($products as $product) {
			$name = isset($product['name']) ? $product['name'] : '';
			$url = isset($product['review_url']) ? $product['review_url'] : '';
			$html .= '<tr><td style="padding:14px 0;border-top:1px solid #eee;vertical-align:middle"><strong>' . $this->escape($name) . '</strong>';

			if (!empty($product['model'])) {
				$html .= '<br><span style="color:#777;font-size:13px">' . $this->escape($product['model']) . '</span>';
			}

			$html .= '</td><td align="right" style="padding:14px 0 14px 12px;vertical-align:middle">';

			if ($url !== '') {
				$html .= '<a href="' . $this->escape($url) . '" style="display:inline-block;padding:11px 16px;border-radius:5px;background:#2563eb;color:#fff;text-decoration:none;font-size:14px;font-weight:bold">' . $this->escape($button) . '</a>';
			}

			$html .= '</td></tr>';
		}

		return $html . '</table>';
	}

	private function htmlToText($html) {
		$html = preg_replace_callback('/<a\b[^>]*href=(?:"([^"]+)"|\'([^\']+)\')[^>]*>(.*?)<\/a>/is', function($matches) {
			$url = $matches[1] !== '' ? $matches[1] : $matches[2];
			$label = trim(html_entity_decode(strip_tags($matches[3]), ENT_QUOTES, 'UTF-8'));

			return $label . ': ' . html_entity_decode($url, ENT_QUOTES, 'UTF-8');
		}, (string)$html);
		$html = preg_replace('/<\s*br\s*\/?>/i', "\n", (string)$html);
		$html = preg_replace('/<\/(?:p|div|tr|li|h[1-6])>/i', "\n", $html);
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
		$text = preg_replace("/[ \t]+\n/", "\n", $text);
		$text = preg_replace("/\n{3,}/", "\n\n", $text);

		return trim($text);
	}

	private function escape($value) {
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}
}
