<?php
declare(strict_types=1);
namespace App\Services;

/** Published information and capability-scoped anonymous carts; never an actor, owner grant or private registry. */
final class PublicMcpService
{
    // https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/streamable-http
    public const PROTOCOL = '2026-07-28';
    public const LEGACY_PROTOCOL = '2025-11-25';
    public const SUPPORTED_PROTOCOLS = [self::PROTOCOL,self::LEGACY_PROTOCOL];
    public const MAX_REQUEST_BYTES = 65536;
    public const UI_URI = 'ui://bapx/public-knowledge.html';
    public const SHOPPING_UI_URI = 'ui://bapx/public-shopping.html';
    public const UI_MIME = 'text/html;profile=mcp-app';
    public const SURFACE_META = 'in.bapx/publicSurface';
    public const SHOPPING_META = 'in.bapx/shoppingView';
    public const SHOPPING_ERROR_META = 'in.bapx/shoppingError';
    private const INFO = ['name'=>'bapx-public-knowledge','version'=>'1.0.0'];
    private const INSTRUCTIONS = 'Read only published products, articles, contact details and enquiry options. '
        . 'Search the requested product or topic; an empty result is not permission to substitute unrelated items. Follow nextOffset for search pages. '
        . 'For product detail or comparison, reuse up to three exact raw slugs decoded once from returned /product/ paths in slugs, with an empty query, offset 0 and a limit covering the selection. Missing or unpublished selections are omitted. '
        . 'Use returned INR prices, availability, descriptions, optional images, highlights and specifications without inventing claims. Resolve relative paths against this website origin. '
        . 'A compatible MCP Apps host can render the linked public cards; all tools also return readable text and structured data. '
        . 'Use create_cart, get_cart and set_cart_quantity for an anonymous cart. Retain its cartToken privately and use the current expectedRevision for an absolute quantity; zero removes a line. Cart capabilities expire after 24 hours. Never print a capability or put it in a URL. '
        . 'A supporting chat view may share currentCart and a separateBuyNowCart in structured model context. Keep these carts separate; read the chosen cart before a later change and use its current revision. '
        . 'review_checkout validates the buyer-provided delivery details and coupon, then returns a short-lived authoritative quote in integer paise. Do not infer or invent delivery details. Cart item totals remain estimates until this review. '
        . 'Call prepare_checkout_payment only after the buyer explicitly confirms the exact quote and amount, with confirm true and the quoted revision. Reuse its durable attempt; never create another payment after an unknown outcome. The linked payment page is a separate explicit payment-only step, not the checkout form. No card details, CVV or UPI PIN are accepted. '
        . 'Retain paymentToken privately for get_checkout_status for up to 7 days. A supporting view may share only a structured checkout status capability and verified status, never delivery/contact or coupon values. Refresh status before answering a later payment question. A prepared URL or authorized payment is not a completed order; report captured payment and saved order separately. '
        . 'Open the contact form to send an enquiry. Consultation booking is retired; enquiry options are not bookable appointments. '
        . 'These tools never expose owner reports, customer records, private instructions or conversations, and never submit an enquiry or booking. Connection guidance is at /ai-assistants; /llms.txt is an advisory guide, not an authorization or automatic installation mechanism.';
    private string $origin;

    public function __construct(
        private ?PublicKnowledgeService $knowledge = null,
        private ?PublicA2uiService $presentation = null,
        ?string $publicUrl = null,
        private ?RemoteCartService $shopping = null,
        private ?RemoteCheckoutService $checkout = null
    ) {
        $this->origin = PublicSiteUrlService::origin($publicUrl);
    }

    public function origin(): string { return $this->origin; }

    /** Public discovery shares the actual transport registry and current module gates. */
    public function toolDefinitions(): array { return $this->tools(); }

    /** Headers are lower-case HTTP field names. The caller owns HTTP I/O and Origin validation. */
    public function handle(string $body, array $headers): array
    {
        if (strlen($body)>self::MAX_REQUEST_BYTES) return self::error(413,null,-32600,'Request too large.');
        try { $request=json_decode($body,false,32,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return self::error(400,null,-32700,'Parse error.'); }
        if (!$request instanceof \stdClass || ($request->jsonrpc??null)!=='2.0'
            || !is_string($request->method??null) || $request->method==='') {
            return self::error(400,null,-32600,'Invalid request.');
        }
        $params=$request->params??null;
        // A legacy profile is explicit: its version header, or an initialize request without modern framing.
        // Never downgrade a malformed modern request into the more permissive handshake-based profile.
        $modernFraming=isset($headers['mcp-method']) || isset($headers['mcp-name'])
            || ($params instanceof \stdClass && ($params->_meta??null) instanceof \stdClass
                && (property_exists($params->_meta,'io.modelcontextprotocol/protocolVersion')
                    || property_exists($params->_meta,'io.modelcontextprotocol/clientCapabilities')));
        $versionHeader=$headers['mcp-protocol-version']??null;
        $legacy=!$modernFraming && ($versionHeader===self::LEGACY_PROTOCOL
            || ($request->method==='initialize' && $versionHeader===null));
        if (!property_exists($request,'id')) {
            // Stateless legacy initialization has no persistent effect. No JSON-RPC notification gets a reply.
            $accepted=$legacy && $request->method==='notifications/initialized'
                && (!property_exists($request,'params') || $params instanceof \stdClass);
            return ['status'=>$accepted ? 202 : 400,'body'=>null];
        }
        $id=$request->id;
        if (!is_int($id) && !is_string($id)) return self::error(400,null,-32600,'Invalid request ID.');
        if ($legacy) return $this->handleLegacy($request,$headers);
        foreach (['mcp-protocol-version','mcp-method'] as $required) {
            if (!isset($headers[$required]) || !is_string($headers[$required]) || $headers[$required]==='') {
                return self::error(400,$id,-32020,'Missing required MCP headers. This endpoint supports '.self::PROTOCOL.'.');
            }
            if (!preg_match('/^[\x21-\x7E]+$/D',$headers[$required])) {
                return self::error(400,$id,-32020,'Malformed MCP header.');
            }
        }
        if (!$params instanceof \stdClass || !($params->_meta??null) instanceof \stdClass) {
            return self::error(400,$id,-32602,'Required request metadata is missing.');
        }
        $meta=$params->_meta;
        $version=$meta->{'io.modelcontextprotocol/protocolVersion'}??null;
        $capabilities=$meta->{'io.modelcontextprotocol/clientCapabilities'}??null;
        $info=$meta->{'io.modelcontextprotocol/clientInfo'}??null;
        if (!is_string($version) || !$capabilities instanceof \stdClass
            || ($info!==null && (!$info instanceof \stdClass || !is_string($info->name??null) || !is_string($info->version??null)))) {
            return self::error(400,$id,-32602,'Invalid request metadata.');
        }
        if ($headers['mcp-protocol-version']!==$version || $headers['mcp-method']!==$request->method) {
            return self::error(400,$id,-32020,'MCP headers do not match the request.');
        }
        if (in_array($request->method,['tools/call','resources/read','prompts/get'],true)) {
            $name=$request->method==='resources/read' ? ($params->uri??null) : ($params->name??null);
            if (!is_string($name) || self::headerName($headers['mcp-name']??null)!==$name) {
                return self::error(400,$id,-32020,'Mcp-Name is missing or does not match the request.');
            }
        }
        if ($version!==self::PROTOCOL) {
            return self::error(400,$id,-32022,'Unsupported protocol version for modern framing.', ['requested'=>$version,'supported'=>self::SUPPORTED_PROTOCOLS]);
        }
        try {
            $result=match($request->method) {
                'server/discover'=>$this->complete([
                    'supportedVersions'=>self::SUPPORTED_PROTOCOLS,
                    'capabilities'=>self::capabilities(),
                    'instructions'=>self::INSTRUCTIONS,
                ],true),
                default=>$this->dispatch($request->method,$params),
            };
            if ($result===null) return self::error(404,$id,-32601,'Method not found. This endpoint supports '.self::PROTOCOL.'.');
            return ['status'=>200,'body'=>['jsonrpc'=>'2.0','id'=>$id,'result'=>$result]];
        } catch (\InvalidArgumentException) {
            return self::error(400,$id,-32602,'Invalid method parameters or unavailable public capability.');
        } catch (\Throwable $error) {
            // Exception text can contain database or provider details. Neither log nor return it here.
            error_log('[public_mcp] unavailable class='.$error::class);
            return self::error(503,$id,-32603,'Public information is temporarily unavailable.');
        } finally {
            DatabaseService::releaseIdleConnection();
        }
    }

    /** A narrow 2025-11-25 Streamable HTTP profile, with no MCP/PHP session or stored client state. */
    private function handleLegacy(\stdClass $request,array $headers): array
    {
        $id=$request->id;
        $params=property_exists($request,'params') ? $request->params : (object)[];
        if (!$params instanceof \stdClass || (property_exists($params,'_meta') && !$params->_meta instanceof \stdClass)) {
            return self::error(400,$id,-32602,'Invalid legacy method parameters.');
        }
        try {
            if ($request->method==='initialize') {
                if (!is_string($params->protocolVersion??null) || $params->protocolVersion===''
                    || !($params->capabilities??null) instanceof \stdClass
                    || !($params->clientInfo??null) instanceof \stdClass
                    || !is_string($params->clientInfo->name??null) || !is_string($params->clientInfo->version??null)
                    || (isset($headers['mcp-protocol-version']) && $headers['mcp-protocol-version']!==$params->protocolVersion)) {
                    throw new \InvalidArgumentException();
                }
                // Legacy negotiation may offer the supported revision when the requested revision differs.
                $result=['protocolVersion'=>self::LEGACY_PROTOCOL,'capabilities'=>self::capabilities(),
                    'serverInfo'=>self::INFO,'instructions'=>self::INSTRUCTIONS];
            } elseif ($request->method==='ping') {
                $result=(object)[];
            } else {
                $result=$this->dispatch($request->method,$params);
                if ($result===null) return self::error(404,$id,-32601,'Method not found for MCP '.self::LEGACY_PROTOCOL.'.');
                // Shared public projections are identical; only the modern wire envelope differs.
                unset($result['resultType'],$result['ttlMs'],$result['cacheScope'],$result['_meta']['io.modelcontextprotocol/serverInfo']);
                if (empty($result['_meta'])) unset($result['_meta']);
            }
            return ['status'=>200,'body'=>['jsonrpc'=>'2.0','id'=>$id,'result'=>$result]];
        } catch (\InvalidArgumentException) {
            return self::error(400,$id,-32602,'Invalid method parameters or unavailable public capability.');
        } catch (\Throwable $error) {
            error_log('[public_mcp] unavailable class='.$error::class);
            return self::error(503,$id,-32603,'Public information is temporarily unavailable.');
        } finally {
            DatabaseService::releaseIdleConnection();
        }
    }

    private function dispatch(string $method,\stdClass $params): ?array
    {
        return match($method) {
            'tools/list'=>$this->listTools($params),
            'tools/call'=>$this->callTool($params),
            'resources/list'=>$this->listResources($params),
            'resources/templates/list'=>$this->emptyTemplates($params),
            'resources/read'=>$this->readResource($params),
            default=>null,
        };
    }

    private static function capabilities(): array
    {
        return ['tools'=>(object)[],'resources'=>(object)[],
            'extensions'=>['io.modelcontextprotocol/ui'=>(object)[]]];
    }

    private static function headerName(mixed $value): ?string
    {
        if (!is_string($value) || $value==='' || trim($value)!==$value || preg_match('/[^\x20-\x7E]/',$value)) return null;
        if (str_starts_with($value,'=?base64?') && str_ends_with($value,'?=')) {
            $decoded=base64_decode(substr($value,9,-2),true);
            return is_string($decoded) && mb_check_encoding($decoded,'UTF-8') ? $decoded : null;
        }
        return $value;
    }

    private function complete(array $result, bool $cacheable=false): array
    {
        $result=['resultType'=>'complete']+$result;
        $result['_meta']=($result['_meta']??[])+['io.modelcontextprotocol/serverInfo'=>self::INFO];
        if ($cacheable) $result+=['ttlMs'=>0,'cacheScope'=>'public'];
        return $result;
    }

    public static function error(int $status, mixed $id, int $code, string $message, ?array $data=null): array
    {
        $body=['jsonrpc'=>'2.0'];
        if (is_int($id) || is_string($id)) $body['id']=$id;
        $body['error']=['code'=>$code,'message'=>$message];
        if ($data!==null) $body['error']['data']=$data;
        return ['status'=>$status,'body'=>$body];
    }

    private function tools(): array
    {
        $modules=(new SettingsService())->modules(requireReadable:true);
        $definitions=[];
        if ($modules['shop']) $definitions[]=['search_public_products','Search published products',
            'Search the published catalogue by query, following nextOffset. Returns current INR prices, availability, descriptions and official product/photo URLs. For exact detail or comparison use up to three raw slugs from returned product paths, with query empty, offset 0 and limit covering the selection; only those published products return, with bounded galleries, highlights and specifications. Compatible chat views offer user-operated anonymous carts and an inline checkout form and order review.',
            PublicKnowledgeService::productInputSchema(),PublicKnowledgeService::productOutputSchema()];
        if ($modules['blog']) $definitions[]=['search_public_articles','Search published articles and guides',
            'Find published blog articles and customer guides. Excludes drafts, scheduled and private help.',
            PublicKnowledgeService::articleInputSchema(),PublicKnowledgeService::articleOutputSchema()];
        $definitions[]=['get_public_contact','Read public contact details',
            'Read the business name, public phone numbers, email, hours and contact-page link. Does not send a message.',
            PublicKnowledgeService::contactInputSchema(),PublicKnowledgeService::contactOutputSchema()];
        $definitions[]=['list_public_services','Find public enquiry services',
            'Find the published product, temple, order-help and general enquiry options and their contact-form links. Does not book or submit an enquiry.',
            PublicKnowledgeService::serviceInputSchema(),PublicKnowledgeService::serviceOutputSchema()];
        $tools=array_map(static function(array $definition): array {
            [$name,$title,$description,$input,$output]=$definition;
            $tool=['name'=>$name,'title'=>$title,'description'=>$description,'inputSchema'=>$input,'outputSchema'=>$output,
                'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false]];
            // Metadata is safe public data. Hosts without Apps ignore it and use the meaningful text result.
            // This also supports reference hosts that do not advertise their UI capability at discovery.
            $tool['_meta']=['ui'=>['resourceUri'=>$name==='search_public_products'?self::SHOPPING_UI_URI:self::UI_URI,
                'visibility'=>['model','app']], 'in.bapx/access'=>['scope'=>'public_information','authorization'=>'none']];
            return $tool;
        },$definitions);
        $commerce=[
            ['create_cart','Create an anonymous cart',
                'Create one empty anonymous cart. Returns a cartToken capability once; retain it for later calls without printing it or placing it in URLs. It expires after 24 hours and grants only this cart. Creating again makes a separate cart. This is not an order or stock reservation.',
                self::cartToolInputSchema('create_cart'),RemoteCartService::cartOutputSchema(),false,false,false,false,'anonymous_cart','none'],
            ['get_cart','Read one anonymous cart',
                'Read current published items, availability, absolute quantities and estimated INR item total for the supplied cartToken. Read before a later mutation and use its revision. Cart access expires after 24 hours; an unavailable item makes the estimate unavailable. Checkout-locked carts remain readable. No customer or owner account is accessed.',
                self::cartToolInputSchema('get_cart'),RemoteCartService::cartOutputSchema(),true,false,true,false,'anonymous_cart','cart_capability'],
            ['set_cart_quantity','Set one cart quantity',
                'Set an exact published product slug to absolute quantity 0..99 in the supplied anonymous cart; zero removes it. Requires the observed expectedRevision, with at most 20 distinct lines. An identical immediate retry does not add twice. On conflict read and review before another change. This may replace/remove a line, but creates no order or reservation.',
                self::cartToolInputSchema('set_cart_quantity'),RemoteCartService::cartOutputSchema(),false,true,true,false,'anonymous_cart','cart_capability'],
            ['review_checkout','Review delivery and checkout totals',
                'Review one cart and optionally save a complete buyer-provided delivery address/contact and couponCode as one revision-checked update; an empty couponCode clears it. Do not invent personal details. Returns only delivery completeness/region, coupon presence and server-authoritative integer-paise totals and a short-lived quote, never the full personal details. No payment is created. Review the exact quote with the buyer before preparing payment.',
                RemoteCheckoutService::reviewInputSchema(),RemoteCheckoutService::reviewOutputSchema(),false,true,false,false,'anonymous_checkout','cart_capability'],
            ['prepare_checkout_payment','Prepare the confirmed checkout payment',
                'Only after the buyer explicitly confirms the exact reviewed quote and amount: submit cartToken, expectedRevision, quoteId and confirm true. Creates a durable payment attempt before contacting Razorpay and locks this checkout. Exact retries reuse that attempt; an uncertain provider result must not be blindly retried as a new purchase. Returns a status-only paymentToken valid up to 7 days and, when ready, a merchant payment-only URL for a separate explicit user action. No raw payment credentials are accepted and a prepared attempt is not payment success.',
                RemoteCheckoutService::preparePaymentInputSchema(),RemoteCheckoutService::preparePaymentOutputSchema(),false,false,true,true,'anonymous_checkout','cart_capability_and_explicit_quote_confirmation'],
            ['get_checkout_status','Verify payment and order status',
                'Refresh one checkout using its status-only paymentToken, valid up to 7 days. Queries the payment provider and may persist or reconcile the already-verified paid order. It cannot initiate payment or change delivery details. Distinguish captured payment, a saved order, and an order still pending save; return the verified current status without guessing from an opened payment URL.',
                RemoteCheckoutService::statusInputSchema(),RemoteCheckoutService::statusOutputSchema(),false,false,true,true,'checkout_status','payment_status_capability'],
        ];
        foreach ($commerce as [$name,$title,$description,$input,$output,$readOnly,$destructive,$idempotent,$openWorld,$scope,$authorization]) {
            if (!$modules['shop'] && $name!=='get_checkout_status') continue;
            $tools[]=['name'=>$name,'title'=>$title,'description'=>$description,'inputSchema'=>$input,'outputSchema'=>$output,
                'annotations'=>['readOnlyHint'=>$readOnly,'destructiveHint'=>$destructive,'idempotentHint'=>$idempotent,'openWorldHint'=>$openWorld],
                '_meta'=>['ui'=>['resourceUri'=>self::SHOPPING_UI_URI,'visibility'=>['model','app']],
                    'in.bapx/access'=>['scope'=>$scope,'authorization'=>$authorization]]];
        }
        return $tools;
    }

    /** Focused public tool contracts reuse the canonical internal cart fields. */
    private static function cartToolInputSchema(string $name): array
    {
        $keys=match($name) {
            'create_cart'=>[], 'get_cart'=>['cartToken'],
            'set_cart_quantity'=>['cartToken','slug','quantity','expectedRevision'],
        };
        $properties=array_intersect_key(RemoteCartService::cartInputSchema()['properties'],array_flip($keys));
        return ['type'=>'object','additionalProperties'=>false,'required'=>$keys,'properties'=>$properties ?: (object)[]];
    }

    private static function noCursor(\stdClass $params): void
    {
        if (property_exists($params,'cursor')) throw new \InvalidArgumentException();
    }

    private function listTools(\stdClass $params): array
    {
        self::noCursor($params);
        return $this->complete(['tools'=>$this->tools()],true);
    }

    private function callTool(\stdClass $params): array
    {
        $name=$params->name??null;
        $commerce=['create_cart','get_cart','set_cart_quantity','review_checkout','prepare_checkout_payment','get_checkout_status'];
        if (!is_string($name) || !in_array($name,array_merge(['search_public_products','search_public_articles','get_public_contact','list_public_services'],$commerce),true)
            || (property_exists($params,'arguments') && !$params->arguments instanceof \stdClass)) {
            throw new \InvalidArgumentException();
        }
        if ($name!=='get_checkout_status' && in_array($name,array_merge(['search_public_products','search_public_articles'],$commerce),true)) {
            $module=$name==='search_public_articles' ? 'blog' : 'shop';
            if (!(new SettingsService())->modules(requireReadable:true)[$module]) throw new \InvalidArgumentException();
        }
        $arguments=get_object_vars($params->arguments??(object)[]);
        if (in_array($name,$commerce,true)) return $this->callShoppingTool($name,$arguments);
        $contact=$name==='get_public_contact';$products=$name==='search_public_products';
        $query=$arguments['query']??''; $offset=$arguments['offset']??0; $limit=$arguments['limit']??5;
        $slugs=$arguments['slugs']??[];
        $allowed=$contact ? [] : ($products?['query','offset','limit','slugs']:['query','offset','limit']);
        if (array_diff(array_keys($arguments),$allowed) || !is_string($query) || !mb_check_encoding($query,'UTF-8') || mb_strlen($query)>120
            || !is_int($offset) || $offset<0 || $offset>2147483647 || !is_int($limit) || $limit<1 || $limit>20
            || !is_array($slugs)
            || array_filter($arguments,static fn(mixed $value):bool=>$value===null)) {
            return $this->complete(['isError'=>true,'content'=>[['type'=>'text','text'=>$contact
                ? 'Contact details take no arguments.' : 'Use query up to 120 characters, offset 0..2147483647 and limit 1..20.']]]);
        }
        $source=$this->knowledge??=new PublicKnowledgeService(publicUrl:$this->origin);
        $view=match($name) {
            'search_public_products'=>'products', 'search_public_articles'=>'articles',
            'get_public_contact'=>'contact', 'list_public_services'=>'services',
        };
        try {
            $data=$contact ? $source->contact() : ($products
                ? $source->products($query,$offset,$limit,$slugs) : $source->{$view}($query,$offset,$limit));
        } catch (\InvalidArgumentException) {
            return $this->complete(['isError'=>true,'content'=>[['type'=>'text',
                'text'=>'Use valid search arguments, or up to three unique exact product slugs with an empty query, offset 0 and a limit covering the selection.']]]);
        }
        $result=['structuredContent'=>$data, 'content'=>[['type'=>'text','text'=>$this->textAnswer($view,$data)]]];
        if ($products) $result['_meta']=[self::SHOPPING_META=>'products'];
        try {
            $layout=$products&&count($slugs)>1?'compare':($products&&count($slugs)===1?'detail':'auto');
            $surface=($this->presentation??=new PublicA2uiService($source))->fromResult($view,$data,4,$layout,count($slugs));
            $result['_meta'][self::SURFACE_META]=$surface;
        } catch (\Throwable $error) {
            // Presentation is progressive enhancement; retain the verified public data and text.
            error_log('[public_mcp] presentation unavailable class='.$error::class);
        }
        if (strlen(json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))>65536) {
            return $this->complete(['isError'=>true,'content'=>[['type'=>'text','text'=>'Results are too large. Reduce limit and try again.']]]);
        }
        return $this->complete($result);
    }

    private function callShoppingTool(string $name,array $arguments): array
    {
        try {
            if (in_array($name,['create_cart','get_cart','set_cart_quantity'],true)) {
                $schema=self::cartToolInputSchema($name);
                if (array_diff(array_keys($arguments),array_keys((array)$schema['properties']))
                    || array_diff($schema['required'],array_keys($arguments))) {
                    throw new RemoteCartException('invalid_arguments','Use only the required arguments for this cart action.');
                }
                $operation=match($name) {'create_cart'=>'create','get_cart'=>'get','set_cart_quantity'=>'set_quantity'};
                $data=($this->shopping??=new RemoteCartService(publicUrl:$this->origin))->shoppingCart(['operation'=>$operation]+$arguments);
                $kind='cart';
            } else {
                if (($arguments['delivery']??null) instanceof \stdClass) $arguments['delivery']=get_object_vars($arguments['delivery']);
                $service=$this->checkout??=new RemoteCheckoutService(publicUrl:$this->origin);
                [$kind,$data]=match($name) {
                    'review_checkout'=>['review',$service->reviewCheckout($arguments)],
                    'prepare_checkout_payment'=>['payment',$service->preparePayment($arguments)],
                    'get_checkout_status'=>['status',$service->checkoutStatus($arguments)],
                };
            }
            // The anonymous capability is intentionally model-visible in structuredContent on creation.
            // Human text and the DOM never receive a serialized result containing that capability.
            $lines=[];
            if ($kind==='cart' || $kind==='review') {
                $cart=$kind==='cart' ? $data : $data['cart'];
                $lines[]='Anonymous cart: '.$cart['itemCount'].' item(s).';
                foreach($cart['items'] as $item) {
                    $lines[]=$item['name'].' — quantity '.$item['quantity'].' — '.($item['available']
                        ? 'INR '.number_format($item['lineTotalInr'],2,'.','') : 'currently unavailable');
                }
                $lines[]=$cart['estimatedTotalInr']===null ? 'Item estimate unavailable until the listed items are resolved.'
                    : 'Estimated item total: INR '.number_format($cart['estimatedTotalInr'],2,'.','');
            }
            if ($kind==='review') {
                $lines[]=$data['deliveryComplete'] ? 'Delivery details are complete.' : 'Delivery details are incomplete; review the required fields.';
                $lines[]=$data['couponApplied'] ? 'A coupon is applied.' : 'No coupon is applied.';
                $totals=$data['totals'];
                $lines[]='Reviewed total: INR '.number_format($totals['totalPaise']/100,2,'.','').' including applicable GST.';
                if ($data['quote']!==null) $lines[]='Quote expires at '.$data['quote']['expiresAt'].'. Confirm this exact quote before preparing payment.';
            } elseif ($kind==='payment') {
                $lines[]='Checkout payment stage: '.$data['stage'].'. Amount: INR '.number_format($data['amountPaise']/100,2,'.','').'.';
                if (isset($data['paymentUrl'])) $lines[]='Payment-only continuation, after explicit buyer review: '.$data['paymentUrl'];
                $lines[]='A prepared attempt does not prove payment or order success. Refresh payment status to verify.';
            } elseif ($kind==='status') {
                $lines[]='Verified payment status: '.$data['paymentStatus'].'. Order status: '.$data['orderStatus'].'.';
                $lines[]='Amount: INR '.number_format($data['amountPaise']/100,2,'.','').'. Checked at '.$data['checkedAt'].'.';
                if ($data['orderStatus']==='saved' && isset($data['orderReference'])) $lines[]='Saved order reference: '.$data['orderReference'];
            }
            $lines[]=$data['notice'];
            return $this->complete(['structuredContent'=>$data,'content'=>[['type'=>'text','text'=>implode("\n",$lines)]],
                '_meta'=>[self::SHOPPING_META=>$kind]]);
        } catch (RemoteCartException $error) {
            return $this->complete(['isError'=>true,'content'=>[['type'=>'text','text'=>$error->getMessage()]],
                '_meta'=>[self::SHOPPING_ERROR_META=>['code'=>$error->errorCode,'httpStatus'=>$error->httpStatus,'retryAfter'=>$error->retryAfter]]]);
        }
    }

    private function textAnswer(string $view, array $data): string
    {
        // Include an absolute source origin so plain MCP clients can use the relative canonical paths.
        return 'Published '.$view.' from '.$this->origin.'. Relative paths refer to this website.'."\n"
            .json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    private function resourceDefinition(bool $shopping=false): array
    {
        if ($shopping) return ['uri'=>self::SHOPPING_UI_URI,'name'=>'public-shopping','title'=>'Products, cart and checkout',
            'description'=>'Official product photos, user-operated cart, inline delivery form and quote review, and verified payment/order status.', 'mimeType'=>self::UI_MIME];
        return ['uri'=>self::UI_URI,'name'=>'public-knowledge','title'=>'Public products and information',
            'description'=>'Read-only cards for published products, articles, contact details and services.', 'mimeType'=>self::UI_MIME];
    }

    private function listResources(\stdClass $params): array
    {
        self::noCursor($params);
        return $this->complete(['resources'=>[$this->resourceDefinition(),$this->resourceDefinition(true)]],true);
    }

    private function emptyTemplates(\stdClass $params): array
    {
        self::noCursor($params);
        return $this->complete(['resourceTemplates'=>[]],true);
    }

    private function readResource(\stdClass $params): array
    {
        if (!in_array($params->uri??null,[self::UI_URI,self::SHOPPING_UI_URI],true)) throw new \InvalidArgumentException();
        $shopping=$params->uri===self::SHOPPING_UI_URI;
        $publicOrigin=$this->origin;
        $renderer=file_get_contents(app_path('assets/a2ui-public-renderer.js'));
        $styles=file_get_contents(app_path('assets/css/a2ui-public.css'));
        $bridge=file_get_contents(app_path('assets/public-mcp-app.js'));
        if ($renderer===false || $styles===false || $bridge===false) throw new \RuntimeException();
        $shoppingScript=$shopping ? file_get_contents(app_path('assets/public-mcp-shopping.js')) : '';
        $checkoutScript=$shopping ? file_get_contents(app_path('assets/checkout-form.js')) : '';
        $commerceStyles=$shopping ? file_get_contents(app_path('assets/css/chat-commerce.css')) : '';
        $deliveryStates=$shopping ? TaxService::states() : [];
        $canShop=$shopping && (new SettingsService())->modules(requireReadable:true)['shop'];
        if ($shoppingScript===false || $checkoutScript===false || $commerceStyles===false) throw new \RuntimeException();
        ob_start();
        try { require app_path('views/public/mcp-app.php'); $html=(string)ob_get_contents(); }
        finally { ob_end_clean(); }
        return $this->complete(['contents'=>[['uri'=>$params->uri,'mimeType'=>self::UI_MIME,'text'=>$html,
            '_meta'=>['ui'=>['prefersBorder'=>true,'csp'=>['connectDomains'=>[], 'resourceDomains'=>[$publicOrigin], 'frameDomains'=>[]]]]]]],true);
    }
}
