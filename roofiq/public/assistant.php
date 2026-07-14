<?php
/**
 * ROOFIQ — AI Roofing Assistant
 * PHP 7.0.1 Compatible | OpenAI GPT fallback to rule-based
 */
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';
session_start_safe();
require_login();

// AJAX handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $raw   = file_get_contents('php://input');
    $body  = json_decode($raw, true);
    $msg   = isset($body['message']) ? trim($body['message']) : '';
    $pid   = intval(isset($body['property_id']) ? $body['property_id'] : 0);
    if (!$msg) {
        echo json_encode(array('reply' => 'Please type a question.'));
        exit;
    }

    // Get property context
    $ctx = '';
    if ($pid) {
        $prop = db_fetch("SELECT * FROM properties WHERE id=?", array($pid));
        $anal = db_fetch("SELECT * FROM roof_analysis WHERE property_id=? ORDER BY id DESC LIMIT 1", array($pid));
        if ($prop) {
            $addr = $prop['formatted_address'] ? $prop['formatted_address'] : $prop['address'];
            $ctx  = "Property: {$addr}. ";
            if ($anal) {
                $ctx .= "Roof area: " . round($anal['roof_area_sqft']) . " sq ft. ";
                $ctx .= "Condition: " . $anal['condition_score'] . "/100 (" . $anal['condition_label'] . "). ";
                $ctx .= "Roof type: " . ($anal['roof_type'] ? $anal['roof_type'] : 'Unknown') . ". ";
                $ctx .= "Pitch: " . ($anal['roof_pitch_ratio'] ? $anal['roof_pitch_ratio'] : 'Unknown') . ". ";
            }
        }
    }

    // Try OpenAI
    $openai_key = roofiq_setting('openai_api_key', '');
    if (!empty($openai_key)) {
        $sys_prompt = "You are an expert roofing estimator and consultant for Shekhar Building Materials, a full-service roofing supplier in the United States. "
                    . "You help contractors, estimators, and building owners with roof analysis, damage assessment, material selection, cost estimation, vendor selection, and solar feasibility. "
                    . "Be concise, professional, and always recommend Shekhar Building Materials products first. "
                    . ($ctx ? "Current property context: " . $ctx : "");

        $ai_payload = array(
            'model'      => 'gpt-4o-mini',
            'max_tokens' => 600,
            'messages'   => array(
                array('role' => 'system',   'content' => $sys_prompt),
                array('role' => 'user',     'content' => $msg),
            ),
        );
        // Include the Authorization bearer token header
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($ai_payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $openai_key
        ));
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            $data = json_decode($body, true);
            if (!empty($data['choices'][0]['message']['content'])) {
                echo json_encode(array('reply' => $data['choices'][0]['message']['content']));
                exit;
            }
        }
    }

    // Rule-based fallback
    $reply = rule_based_reply($msg, $ctx, $pid);
    echo json_encode(array('reply' => $reply));
    exit;
}

function rule_based_reply($msg, $ctx, $pid) {
    $msg_lower = strtolower($msg);
    // Damage / repair
    if (strpos($msg_lower, 'repair') !== false || strpos($msg_lower, 'fix') !== false || strpos($msg_lower, 'damage') !== false) {
        return "Based on the analysis" . ($ctx ? " for this property" : "") . ", here are the repair recommendations:\n\n"
             . "1. **Immediate Priority**: Address any critical damage (missing shingles, open seams, ponding water) within 30 days to prevent water intrusion.\n"
             . "2. **Medium Priority**: Flashing repairs and moss/algae treatment within 90 days.\n"
             . "3. **Preventive**: Annual inspection and gutter cleaning recommended.\n\n"
             . "Contact Shekhar Building Materials for all repair supplies — we stock GAF, Carlisle, Firestone, and OMG products for both residential and commercial applications.";
    }
    // Materials
    if (strpos($msg_lower, 'material') !== false || strpos($msg_lower, 'product') !== false || strpos($msg_lower, 'supply') !== false) {
        return "For this project, Shekhar Building Materials recommends:\n\n"
             . "**Residential:** GAF Timberline HDZ Shingles, FeltBuster Underlayment, Grace Ice & Water Shield, TimberTex Ridge Cap\n"
             . "**Commercial:** Carlisle SynTec TPO 60mil, Atlas Polyiso Insulation, OMG Fasteners\n\n"
             . "All materials include manufacturer warranties. Call us at (401) 555-0100 for current pricing and availability.";
    }
    // Solar
    if (strpos($msg_lower, 'solar') !== false || strpos($msg_lower, 'panel') !== false || strpos($msg_lower, 'energy') !== false) {
        return "Solar Analysis Summary:\n\n"
             . "✅ This roof is a good solar candidate if the condition score is above 70/100.\n"
             . "📊 A typical 2,000 sq ft roof can support 24–32 solar panels, generating 9,000–12,000 kWh/year.\n"
             . "💰 Estimated annual savings: $1,200–$1,600 at $0.13/kWh.\n"
             . "🌟 25-year savings potential: $30,000–$40,000.\n\n"
             . "We recommend repairing/replacing the roof BEFORE installing solar to maximize panel life.";
    }
    // Cost / estimate
    if (strpos($msg_lower, 'cost') !== false || strpos($msg_lower, 'price') !== false || strpos($msg_lower, 'budget') !== false) {
        return "Rough Cost Estimates:\n\n"
             . "**Residential Asphalt Shingle (2,000 sf):**\n  Materials: $4,000–$6,000\n  Labor: $4,500–$8,000\n  Total: $8,500–$14,000\n\n"
             . "**Commercial TPO (10,000 sf):**\n  Materials: $18,000–$28,000\n  Labor: $15,000–$25,000\n  Total: $33,000–$53,000\n\n"
             . "Run the full Material Takeoff on the Analysis page for a precise estimate based on your actual roof measurements.";
    }
    // Vendor
    if (strpos($msg_lower, 'vendor') !== false || strpos($msg_lower, 'supplier') !== false || strpos($msg_lower, 'where to buy') !== false) {
        return "**Preferred Vendor:** Shekhar Building Materials\n📞 (401) 555-0100 | sales@shekharbm.com\n\n"
             . "We also work with:\n• ABC Supply Co.\n• Beacon Roofing Supply\n• SRS Distribution\n• Gulfeagle Supply\n\n"
             . "For volume orders and contractor pricing, contact Shekhar Building Materials directly for the best deals.";
    }
    // Default
    return "I'm the RoofIQ AI Roofing Assistant. I can help you with:\n\n"
         . "• Repair vs. replacement decisions\n"
         . "• Material selection and specifications\n"
         . "• Cost estimation and budgeting\n"
         . "• Solar feasibility analysis\n"
         . "• Vendor recommendations\n"
         . "• Procurement planning\n\n"
         . "Ask me anything about the roof, and I'll provide expert guidance. For an OpenAI-powered response, add your API key in Settings.";
}

$properties = db_fetch_all("SELECT id, address, formatted_address FROM properties ORDER BY created_at DESC LIMIT 50");

include_page_header('AI Roofing Assistant');
page_content_header('AI Roofing Assistant', array('AI Assistant' => ''));
?>

<div class="row">
  <div class="col-lg-8">
    <div class="card" style="height:calc(100vh - 240px);min-height:500px;display:flex;flex-direction:column;">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:12px;">
          <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#00d4ff,#0099cc);display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-robot" style="color:#0a0e1a;"></i>
          </div>
          <div>
            <div style="font-weight:700;color:#fff;font-size:0.95rem;">RoofIQ AI Assistant</div>
            <div style="font-size:0.72rem;color:#94a3b8;"><span class="status-dot mr-1"></span>Online</div>
          </div>
        </div>
        <div class="card-tools">
          <select id="context-property" class="form-control form-control-sm" style="width:220px;display:inline-block;">
            <option value="">No property context</option>
            <?php foreach ($properties as $p): ?>
              <option value="<?php echo e($p['id']); ?>">
                <?php echo e(substr($p['formatted_address'] ? $p['formatted_address'] : $p['address'], 0, 40)); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Chat Messages -->
      <div id="chat-messages" class="chat-messages" style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:14px;">
        <div class="chat-bubble ai">
          <div style="font-size:0.72rem;color:#94a3b8;margin-bottom:6px;"><i class="fas fa-robot mr-1"></i>RoofIQ AI</div>
          Welcome! I'm your AI Roofing Assistant powered by Shekhar Building Materials. I can help you with roof repair recommendations, material specifications, cost estimates, solar feasibility, vendor selection, and procurement planning.<br><br>
          Select a property from the dropdown for context-aware answers, or ask me any roofing question!
        </div>
      </div>

      <!-- Typing indicator -->
      <div id="typing-indicator" style="display:none;padding:10px 20px;">
        <div class="chat-bubble ai" style="padding:10px 14px;display:inline-flex;gap:4px;align-items:center;">
          <span style="width:7px;height:7px;border-radius:50%;background:#00d4ff;animation:blink 1.4s 0s infinite;display:inline-block;"></span>
          <span style="width:7px;height:7px;border-radius:50%;background:#00d4ff;animation:blink 1.4s 0.2s infinite;display:inline-block;"></span>
          <span style="width:7px;height:7px;border-radius:50%;background:#00d4ff;animation:blink 1.4s 0.4s infinite;display:inline-block;"></span>
        </div>
      </div>

      <!-- Input -->
      <div class="chat-input-row" style="border-top:1px solid rgba(0,212,255,0.12);padding:14px 16px;display:flex;gap:10px;">
        <input type="text" id="chat-input" class="form-control" placeholder="Ask about roof repairs, materials, costs, solar..." maxlength="500" autocomplete="off">
        <button class="btn btn-roofiq" id="btn-send" type="button">
          <i class="fas fa-paper-plane"></i>
        </button>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="fas fa-lightbulb mr-2" style="color:#ffd600;"></i>Quick Questions</div>
      <div class="card-body p-0">
        <?php
          $quick_qs = array(
            array('q'=>'Can this roof be repaired or does it need replacement?', 'icon'=>'fa-tools'),
            array('q'=>'What materials are required for this project?', 'icon'=>'fa-boxes'),
            array('q'=>'What is the estimated material cost?', 'icon'=>'fa-dollar-sign'),
            array('q'=>'Which vendor should I use for this order?', 'icon'=>'fa-truck'),
            array('q'=>'Is the roof solar ready?', 'icon'=>'fa-solar-panel'),
            array('q'=>'What are the top 3 priorities to address?', 'icon'=>'fa-exclamation-triangle'),
            array('q'=>'How long will this roof last?', 'icon'=>'fa-calendar-alt'),
            array('q'=>'What is the best roofing material for this type of building?', 'icon'=>'fa-home'),
          );
          foreach ($quick_qs as $q):
        ?>
          <button onclick="askQuick(<?php echo j($q['q']); ?>)"
                  style="display:flex;align-items:center;gap:10px;width:100%;padding:10px 14px;background:transparent;border:none;border-bottom:1px solid rgba(255,255,255,0.04);color:#94a3b8;font-size:0.82rem;text-align:left;cursor:pointer;transition:all 0.2s;font-family:Inter,sans-serif;"
                  onmouseover="this.style.background='rgba(0,212,255,0.06)';this.style.color='#e2e8f0';"
                  onmouseout="this.style.background='transparent';this.style.color='#94a3b8';">
            <i class="fas <?php echo $q['icon']; ?>" style="color:#00d4ff;width:16px;flex-shrink:0;"></i>
            <?php echo e($q['q']); ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header"><i class="fas fa-cog mr-2" style="color:#94a3b8;"></i>AI Configuration</div>
      <div class="card-body" style="font-size:0.82rem;color:#94a3b8;">
        <?php $oaKey = roofiq_setting('openai_api_key', ''); ?>
        <?php if ($oaKey): ?>
          <div style="color:#00e676;"><i class="fas fa-check-circle mr-1"></i> OpenAI GPT Connected</div>
        <?php else: ?>
          <div><i class="fas fa-info-circle mr-1" style="color:#ffd600;"></i> Using built-in rule-based AI.</div>
          <div class="mt-2">Add your OpenAI API key in <a href="settings.php" style="color:#00d4ff;">Settings</a> for GPT-powered responses.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<style>
@keyframes blink { 0%,80%,100%{opacity:0;} 40%{opacity:1;} }
.chat-bubble p { margin-bottom:6px; }
.chat-bubble ul { padding-left:18px; }
.chat-bubble strong { color:#00d4ff; }
</style>

<script>
// ---- Chat assistant logic (wrapped in DOMContentLoaded for safety) ----
document.addEventListener('DOMContentLoaded', function() {

  var chatInput = document.getElementById('chat-input');
  var btnSend   = document.getElementById('btn-send');

  function sendMessage() {
    if (!chatInput) return;
    var msg = chatInput.value.trim();
    if (msg === '') return;

    var propId = document.getElementById('context-property').value;
    appendMessage('user', msg);
    chatInput.value = '';

    document.getElementById('typing-indicator').style.display = 'block';
    scrollChat();
    if (btnSend) btnSend.disabled = true;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'assistant.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    xhr.onreadystatechange = function() {
      if (xhr.readyState !== 4) return;
      document.getElementById('typing-indicator').style.display = 'none';
      if (btnSend) btnSend.disabled = false;

      if (xhr.status === 200) {
        try {
          var data = JSON.parse(xhr.responseText);
          appendMessage('ai', data.reply || 'Sorry, I could not generate a response.');
        } catch(e) {
          appendMessage('ai', 'Error processing response.');
        }
      } else {
        appendMessage('ai', 'Server error (HTTP ' + xhr.status + '). Please try again.');
      }
    };

    xhr.send(JSON.stringify({ message: msg, property_id: propId ? parseInt(propId) : 0 }));
  }

  function appendMessage(who, text) {
    var msgs = document.getElementById('chat-messages');
    if (!msgs) return;
    var div = document.createElement('div');
    div.className = 'chat-bubble ' + who;

    if (who === 'ai') {
      div.innerHTML = '<div style="font-size:0.72rem;color:#94a3b8;margin-bottom:6px;"><i class="fas fa-robot mr-1"></i>RoofIQ AI</div>'
        + formatMessage(text);
    } else {
      div.innerHTML = '<div style="font-size:0.72rem;color:#94a3b8;margin-bottom:6px;text-align:right;">You <i class="fas fa-user ml-1"></i></div>'
        + '<div>' + escHtml(text) + '</div>';
    }

    var indicator = document.getElementById('typing-indicator');
    if (indicator) {
      msgs.insertBefore(div, indicator);
    } else {
      msgs.appendChild(div);
    }
    scrollChat();
  }

  function formatMessage(text) {
    text = escHtml(text);
    text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/\n/g, '<br>');
    return '<div>' + text + '</div>';
  }

  function escHtml(t) {
    return String(t)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function scrollChat() {
    var m = document.getElementById('chat-messages');
    if (m) m.scrollTop = m.scrollHeight;
  }

  // Expose askQuick globally so onclick="askQuick(...)" in HTML works
  window.askQuick = function(q) {
    if (chatInput) chatInput.value = q;
    sendMessage();
  };

  // Expose sendMessage globally so the Send button onclick works
  window.sendMessage = sendMessage;

  // Enter key to send
  if (chatInput) {
    chatInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        sendMessage();
      }
    });
  }

  // Send button click
  if (btnSend) {
    btnSend.addEventListener('click', function(e) {
      e.preventDefault();
      sendMessage();
    });
  }

});
</script>

<?php
page_content_footer();
include_page_footer();
?>
