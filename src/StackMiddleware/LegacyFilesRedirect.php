<?php

namespace Drupal\fixfflch\StackMiddleware;

use Drupal\fixfflch\CdnHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Redireciona arquivos no caminho antigo para a CDN.
 *
 * Conteúdos antigos (da época do multisite/Aegir) apontam para
 * https://SITE.fflch.usp.br/sites/SITE.fflch.usp.br/files/..., que não existe
 * mais no servidor. Esses pedidos recebem um redirecionamento permanente para
 * https://cdn.fflch.usp.br/SITE/public/...
 *
 * Roda antes do cache de páginas (prioridade 200), para que um 404 já em
 * cache não seja servido no lugar do redirecionamento, e depois do
 * reverse_proxy (300), para usar o Host repassado pelo Traefik.
 */
class LegacyFilesRedirect implements HttpKernelInterface {

  /**
   * O kernel decorado.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  public function __construct(HttpKernelInterface $http_kernel) {
    $this->httpKernel = $http_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = 1, $catch = TRUE) {
    // 1 = requisição principal: MASTER_REQUEST no Symfony 4 (Drupal 9),
    // MAIN_REQUEST a partir do Symfony 5.3.
    if ($type === 1
      && strpos($request->getPathInfo(), '/sites/') === 0
      && in_array($request->getMethod(), ['GET', 'HEAD'], TRUE)
      && CdnHelper::isEnabled($request->getHost())
      && ($url = CdnHelper::legacyFileUrl($request->getPathInfo()))) {
      $query = $request->getQueryString();
      return new RedirectResponse($url . ($query ? '?' . $query : ''), 301);
    }
    return $this->httpKernel->handle($request, $type, $catch);
  }

}
