let projectNameDiv = document.getElementById("projectNameDiv");

window.addEventListener('load', async (event) => {
  chrome.storage.sync.get('projectId', async (storage) => {
    if (storage.projectId != null) {
      // Show show or hide key depending on highlight state
      chrome.storage.sync.get('nodesHighlighted', (storage) => {
        if (storage.nodesHighlighted === true) {
          getTarjimNodes.style = "display: none;";
          clearTarjimNodes.style = "display: block;";
        }
        else {
          getTarjimNodes.style = "display: block;";
          clearTarjimNodes.style = "display: none;";
        }
      })

      // Highlight nodes on popup open
      let [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
      chrome.scripting.executeScript({
        target: { tabId: tab.id },
        function: highlightTarjimNodes,
      });
    }
    else {
      return;
    }
  });
});

chrome.storage.sync.get('projectName', (storage) => {
  let projectName = storage.projectName;
  if (projectName === 'No tarjim project found') {
    projectNameDiv.innerHTML = projectName;
    // Hide both buttons
    getTarjimNodes.style = "display: none;";
    clearTarjimNodes.style = "display: none";
  }
  else {
    chrome.storage.sync.get('projectId', (storage) => {
      projectNameDiv.innerHTML ='Project: ' +  projectName + '<br>' +'Project id: ' + storage.projectId;
    })
  }
})

let getTarjimNodes = document.getElementById("getTarjimNodes");

getTarjimNodes.addEventListener("click", async () => {
  let [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

  chrome.scripting.executeScript({
    target: { tabId: tab.id },
    function: highlightTarjimNodes,
  });
});

async function highlightTarjimNodes() {
  let assignEventHandler = function (projectId) {
    // Highlight tarjim values 
    // Add event listener to nodes
    let nodes = document.querySelectorAll('[data-tid]');

    let style = `
        .tarjim-extension-injected-subtext:hover {
          background-color: rgb(245, 180, 180);
          color: #2E3193;
          border-radius: .25rem;
          cursor: pointer;
        }
    `;

    let styleNode = document.createElement('style');
    styleNode.id = 'tarjim-extension-injected-style-tag';
    styleNode.innerHTML = style;
    document.head.appendChild(styleNode);

    nodes.forEach((node, index) => {
      let tarjimId = node.getAttribute("data-tid");
      let subtextId = `tarjim-extension-subtext-id-${tarjimId}-${index}`;
      let subtext = document.getElementById(subtextId);
      if (subtext == null) {
        // Insert tarjim id
        subtext = document.createElement("div");
        subtext.innerHTML = `edit in tarjim tid: ${tarjimId}`;
        subtext.id = `tarjim-extension-subtext-id-${tarjimId}-${index}`;
        subtext.style = `
          display: flex;
          font-size: 0.7rem;
          flex-flow: column;
          margin-top: -4px;
          color: darkgrey;
          font-weight: normal;
          text-transform: lowercase;
          width: fit-content;
          margin: auto;
          padding: 5px;
        `;
        subtext.classList.add("tarjim-extension-injected-subtext");
        node.parentNode.insertBefore(subtext, node.nextSibling)

        // Hightlight span
        node.style = `
          border-left: 4px dashed rgb(236, 30, 73);
        `;

        // Add event listener
        subtext.addEventListener("click", function _clickListener(e) {
          // Disable default buttons/clicks
          e.preventDefault();
          // Open tarjim edit page
          window.open(`https://tarjim.io/translationvalues/edit/${projectId}/${tarjimId}?ext=1`, "extension_popup", "width=600,height=700,status=no,scrollbars=yes,resizable=yes");
          // Remove event listener 
        });
      }
    })
    chrome.storage.sync.set({ nodesHighlighted: true });
  }

  chrome.storage.sync.get('projectId', async (storage) => {
    let projectId = storage.projectId;
    // Fallback if project id wasnt set properly
    if (projectId === null) {
      let location = window.location.host;
      location = location.split('.');
      let domain = location.slice(-2) 
      domain = domain.join('.');
      let projectId 
      await fetch(`https://tarjim.io/projects/project_id_from_domain/${domain}`)
        .then(res => res.json())
        .then(response => {
          projectId = response.project_id;
          assignEventHandler(projectId);
        })
    }
    else {
      let projectNameDiv = document.getElementById("project-name");
      assignEventHandler(projectId);
    }
  });
}


let clearTarjimNodes = document.getElementById("clearTarjimNodes");

clearTarjimNodes.addEventListener("click", async() => {
  let [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

  chrome.scripting.executeScript({
    target: { tabId: tab.id },
    function: clearTarjimNodesHighlight,
  });
});

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

// Listen to highlight state change
chrome.storage.sync.onChanged.addListener(() => {
  chrome.storage.sync.get('nodesHighlighted', (storage) => {
    if (storage.nodesHighlighted === true) {
      getTarjimNodes.style = "display: none;";
      clearTarjimNodes.style = "display: block;";
    }
    else {
      getTarjimNodes.style = "display: block;";
      clearTarjimNodes.style = "display: none;";
    }
  })
})
