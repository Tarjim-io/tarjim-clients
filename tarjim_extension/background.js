let _listener = async function() {
    // Get domain name
    let queryOptions = { active: true, currentWindow: true };

    // Get current tab location
    let tab = await getCurrentTab(); 

    let url = tab.url;
    url = new URL(url);
    let host = url.host;

    // For development on localhost uncomment lines below and replace with your project id/name
    // Open chrome://extensions and reload tarjim extension
    if (host === 'localhost:3000') {
        chrome.storage.sync.set({ projectId: '3', projectName: 'panda7.ca'});
        return;
    }

   // host = host.split('.');
    // Get project id from tarjim
    let projectId;
    let updateCacheEndpoint;
    await fetch(`https://app.tarjim.io/api/v1/projects/getProjectIdFromDomain/${host}`)
        .then(res => res.json())
        .then(response => {
            if (response.status === 'success') {
                projectId = response.result.data.project_id;
                updateCacheEndpoint = response.result.data.update_cache_url;
                chrome.storage.sync.set({ projectId: projectId, projectName: host, updateCacheEndpoint: updateCacheEndpoint });
            }
            else {
              chrome.storage.sync.set({ projectId: null, projectName: 'No tarjim project found', updateCacheEndpoint: null});
            }
        })
}

async function getCurrentTab() {
  let queryOptions = { active: true, currentWindow: true };
  let [tab] = await chrome.tabs.query(queryOptions);
  return tab;
}

// Remove highlights and injected elements
function clearTarjimNodesHighlight() {
  // Remove injected style tag
  let styleNodeId = 'tarjim-extension-injected-style-tag';
  let styleNode = document.getElementById(styleNodeId);
  if (styleNode != null) {
    styleNode.remove();
  }

  // Remove injected subtext
  let subtextNodes = document.querySelectorAll('.tarjim-extension-injected-subtext')
  subtextNodes.forEach((node) => {
    node.remove(); 
  })

  // Remove injected style
  let nodes = document.querySelectorAll('[data-tid]');
  nodes.forEach((node, index) => {
    node.style = '';
  })
  chrome.storage.sync.set({ nodesHighlighted: false});
}

// Add listener for current tab location change
chrome.webNavigation.onCompleted.addListener(_listener);

// Add listener for active tab change
chrome.tabs.onActivated.addListener(_listener);

// Add listener for active window change
chrome.windows.onFocusChanged.addListener(_listener);

// Add listener for url changes
chrome.tabs.onUpdated.addListener((tabId) => {
  chrome.scripting.executeScript({
    target: { tabId: tabId },
    function: clearTarjimNodesHighlight,
  });
});
