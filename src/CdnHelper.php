<?php

namespace Drupal\fixfflch;

use Drupal\Core\Site\Settings;

/**
 * Regras de uso da CDN da FFLCH (https://cdn.fflch.usp.br).
 */
class CdnHelper {

  const CDN_DOMAIN = 'https://cdn.fflch.usp.br';

  /**
   * Diz se a CDN deve ser usada para requisições ao host informado.
   *
   * Pode ser forçado no settings.php:
   *   $settings['fixfflch_cdn'] = FALSE; // ou TRUE
   *
   * Sem essa configuração, a CDN fica desligada quando o site é acessado
   * localmente: por IP (127.0.0.1:8000, IP do container etc.), por
   * "localhost" ou por domínios terminados em .localhost, .local ou .test.
   * Em produção os sites são acessados pelo domínio (o Traefik repassa o Host
   * original), então a CDN fica ligada.
   */
  public static function isEnabled($host) {
    $setting = Settings::get('fixfflch_cdn');
    if ($setting !== NULL) {
      return (bool) $setting;
    }
    $is_local = $host === 'localhost'
      || filter_var($host, FILTER_VALIDATE_IP) !== FALSE
      || preg_match('/\.(localhost|local|test)$/', $host);
    return !$is_local;
  }

  /**
   * URL na CDN de um arquivo no caminho antigo (multisite/Aegir).
   *
   * /sites/filosofia.fflch.usp.br/files/eventos/a.png
   *   -> https://cdn.fflch.usp.br/filosofia/public/eventos/a.png
   *
   * O prefixo na CDN é o domínio sem ".fflch.usp.br" (e sem "www."). Caminhos
   * de outros domínios, como sites/default/files, não são convertidos.
   *
   * @param string $path
   *   Caminho da requisição, sem o query string.
   *
   * @return string|null
   *   A URL na CDN, ou NULL se o caminho não for desse formato.
   */
  public static function legacyFileUrl($path) {
    if (!preg_match('#^/sites/([^/]+)/files/(.+)$#', $path, $matches)) {
      return NULL;
    }
    [, $domain, $file] = $matches;
    if (!preg_match('/^(?:www\.)?([a-z0-9-]+(?:\.[a-z0-9-]+)*)\.fflch\.usp\.br$/i', $domain, $site)) {
      return NULL;
    }
    return self::CDN_DOMAIN . '/' . strtolower($site[1]) . '/public/' . $file;
  }

}
