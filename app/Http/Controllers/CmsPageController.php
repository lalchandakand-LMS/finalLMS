<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\View\View;

class CmsPageController extends Controller
{
    public function about(): View
    {
        return $this->page('about', 'About');
    }

    public function contact(): View
    {
        return $this->page('contact', 'Contact');
    }

    public function faq(): View
    {
        $faqs = [];

        for ($i = 1; $i <= 8; $i++) {
            $question = trim((string) Setting::value("faq_{$i}_question", ''));
            $answer = trim((string) Setting::value("faq_{$i}_answer", ''));

            if ($question !== '' && $answer !== '') {
                $faqs[] = compact('question', 'answer');
            }
        }

        return view('pages.faq', [
            'title' => Setting::value('faq_title', 'Frequently asked questions'),
            'faqs' => $faqs,
        ]);
    }

    private function page(string $key, string $fallbackTitle): View
    {
        return view('pages.content', [
            'title' => Setting::value("{$key}_title", $fallbackTitle),
            'body' => Setting::value("{$key}_body", ''),
            'pageKey' => $key,
        ]);
    }
}
