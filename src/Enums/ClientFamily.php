<?php

declare(strict_types=1);

namespace HosmelQ\CanIEmail\Enums;

enum ClientFamily: string
{
    case Aol = 'aol';
    case AppleMail = 'apple-mail';
    case Fastmail = 'fastmail';
    case FreeFr = 'free-fr';
    case Gmail = 'gmail';
    case Gmx = 'gmx';
    case Hey = 'hey';
    case Ionos1and1 = 'ionos-1and1';
    case Laposte = 'laposte';
    case MailRu = 'mail-ru';
    case Orange = 'orange';
    case Outlook = 'outlook';
    case Protonmail = 'protonmail';
    case Rainloop = 'rainloop';
    case SamsungEmail = 'samsung-email';
    case Sfr = 'sfr';
    case Thunderbird = 'thunderbird';
    case TOnlineDe = 't-online-de';
    case WebDe = 'web-de';
    case WpPl = 'wp-pl';
    case Yahoo = 'yahoo';
}
